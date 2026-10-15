/*
 * Capturas automáticas para el manual de usuario.
 * ---------------------------------------------------------------------------
 * Recorre la aplicación con cada rol y guarda los pantallazos en docs/images/
 * con los nombres que el manual ya referencia.
 *
 * Por qué automático y no a mano:
 *   1. Reproducible. Un manual con capturas hechas a mano envejece en silencio:
 *      nadie sabe de qué versión son ni con qué datos se tomaron.
 *   2. Para la entrega formal, las capturas deben corresponder EXACTAMENTE a la
 *      versión entregada. Aquí salen de la misma imagen que se entrega.
 *   3. Los datos son los del DemoSeeder, ficticios. Capturar contra producción
 *      publicaría nombres y DNIs de empleados reales en un documento que se
 *      entrega en papel.
 *
 * Requiere el stack de entrega levantado (ver entrega/docker-compose.entrega.yml):
 *   docker compose -f entrega/docker-compose.entrega.yml -p miboleta_entrega up -d
 *
 * Necesita puppeteer, que vive en el generador de PDF:
 *   NODE_PATH=docs/pdf-generator/node_modules node docs/screenshots/capture.js
 *
 * EJECUTAR POR TANDAS, no del tirón:
 *
 *   ... capture.js empleado     (o sin filtro, que empieza por estas)
 *   ... capture.js admin        # incluye las de Admin Clientes
 *   ... capture.js root
 *   ... capture.js signature
 *
 * De una sola pasada se cuelga al cambiar de rol después de visitar el visor
 * de documentos: PDF.js deja ocupado el hilo principal de la página y la
 * navegación siguiente no vuelve. Se intentó cerrar el modal con Escape,
 * navegar fuera antes de limpiar el almacenamiento y usar 'domcontentloaded';
 * ninguna lo resuelve del todo (y 'domcontentloaded' en el login rompe el
 * CSRF de Sanctum: 419 en todos los inicios de sesión menos el primero).
 * Cada tanda arranca un navegador nuevo, que sí es fiable.
 *
 * Variables: BASE_URL (por defecto http://localhost:9090)
 */

const puppeteer = require('puppeteer');
const path = require('path');
const fs = require('fs');

const BASE_URL = process.env.BASE_URL || 'http://localhost:9090';
const SALIDA = path.resolve(__dirname, '../images');
const FILTRO = process.argv[2] || '';

// Viewport fijo: si cambia entre ejecuciones, todas las capturas se ven
// distintas y el diff del repositorio se vuelve inútil.
const VIEWPORT = { width: 1440, height: 900, deviceScaleFactor: 2 };

const CLAVE = 'password';

const USUARIOS = {
  root: 'admin@email.com',
  adminTenant: 'admin.clientes@miboleta.demo',
  admin: 'admin@corporacionabc.com',
  aprobador: 'aprobador@miboleta.demo',
  empleado: 'juan.perez@corporacionabc.com',
};

/*
 * Cada captura declara con qué rol se toma y a dónde navegar. `espera` permite
 * aguardar a un texto concreto antes de disparar: sin eso se capturan pantallas
 * a medio cargar, con esqueletos en vez de datos.
 */
const CAPTURAS = [
  { archivo: '01_login_page.png', rol: null, ruta: '/login' },

  { archivo: '02_employee_dashboard.png', rol: 'empleado', ruta: '/dashboard' },
  // Antes se llamaba 05_client_vacaciones.png, un nombre que el manual no
  // referencia: el script parecia actualizar la pantalla y en realidad dejaba
  // intacta la que se publica, ademas de dejar un huerfano en docs/images.
  { archivo: '06_employee_vacations.png', rol: 'empleado', ruta: '/vacations' },
  { archivo: '07_vacation_request_form.png', rol: 'empleado', ruta: '/vacations/new' },
  { archivo: '27_perfil.png', rol: 'empleado', ruta: '/profile' },

  /*
   * Visor y firma. Se entra desde el dashboard del empleado, NO desde
   * /documents: esa ruta exige 'documents.view_org', que el empleado no tiene
   * (solo 'documents.view_own'), asi que apuntaba a una pantalla que este rol
   * no llega a ver nunca. El visor vive en /viewer?id=N, con un id que cambia
   * en cada instalacion, por eso se llega pulsando "Ver" y no por URL.
   */
  {
    archivo: '03_document_viewer.png', rol: 'empleado', ruta: '/dashboard',
    // Texto EXACTO, no pulsarTexto: el banner "Ver Documentos Pendientes"
    // contiene "Ver" como substring y esta antes en el DOM, asi que el match
    // por substring pulsaba el banner y nunca abria el visor.
    accion: (page) => pulsarTextoExacto(page, 'Ver'),
  },
  /*
   * Visor rediseñado, con una boleta PENDIENTE: panel derecho con la tarjeta
   * azul "Tu conformidad" y el botón "Firmar documento". 03 enseña el caso
   * firmado (tarjeta verde). Se pulsa "Firmar" (tarjeta pendiente del
   * dashboard), que lleva al visor, y NO se abre el modal. viewport: el visor
   * ocupa el alto de la ventana y PDF.js no deja medir bien la página entera.
   */
  {
    archivo: '36_employee_viewer_pending.png', rol: 'empleado', ruta: '/dashboard',
    viewport: true,
    accion: async (page) => {
      await pulsarTexto(page, 'Firmar');
      await page.waitForFunction(
        () => document.body.innerText.includes('Tu conformidad'),
        { timeout: 15000 },
      );
    },
  },
  { archivo: '08_admin_dashboard.png', rol: 'admin', ruta: '/dashboard' },
  { archivo: '10_admin_users.png', rol: 'admin', ruta: '/users' },
  { archivo: '11_admin_documents.png', rol: 'admin', ruta: '/documents' },
  { archivo: '12_admin_audit.png', rol: 'admin', ruta: '/audit-logs' },
  { archivo: '09_admin_upload_empty.png', rol: 'admin', ruta: '/upload' },
  /*
   * Con archivo procesado: la unica captura del manual que hasta ahora se
   * hacia a mano (y por eso llevaba el branding viejo). No se pulsa "Subir N
   * documentos" en ningun momento: eso mutaria la BD de la demo.
   */
  {
    archivo: '09_admin_upload_filled.png', rol: 'admin', ruta: '/upload',
    accion: async (page) => {
      const input = await page.waitForSelector('input[type="file"]');
      await input.uploadFile(path.resolve(__dirname, '../example/archivo.zip'));

      // El analisis del ZIP es async: se espera a que pinten los badges
      // "Valido" antes de tocar nada mas.
      await page.waitForFunction(
        () => document.body.innerText.includes('Válido'),
        { timeout: 15000 },
      );

      // Los selects de "Configuracion de Carga" estan deshabilitados hasta que
      // hay un preview valido, por eso van despues del upload y no antes.
      //
      // Tipo de Documento es un Select de Radix: sus opciones se montan en un
      // portal como [role="option"], asi que primero se abre el trigger (un
      // <button> normal, lo encuentra el selector por defecto) y luego se
      // pulsa la opcion con el selector explicito, que si no NO la encuentra.
      await pulsarTexto(page, 'Seleccionar tipo...');
      await pulsarTexto(page, 'Boleta de Remuneraciones', '[role="option"]');

      // Periodo: MonthYearPicker, un Popover con botones de mes (no un
      // <select>). Los botones muestran solo las 3 primeras letras del mes.
      await pulsarTexto(page, 'Seleccionar período...');
      await pulsarTexto(page, 'Jul');
    },
  },
  { archivo: '21_admin_batch_list.png', rol: 'admin', ruta: '/batches' },
  {
    archivo: '22_admin_batch_detail_top.png', rol: 'admin', ruta: '/batches',
    accion: (page) => abrirPrimeraFila(page), mitad: 'arriba',
  },
  {
    archivo: '22_admin_batch_detail_bottom.png', rol: 'admin', ruta: '/batches',
    accion: (page) => abrirPrimeraFila(page), mitad: 'abajo',
  },
  {
    archivo: '22_admin_batch_document_view.png', rol: 'admin', ruta: '/batches',
    accion: async (page) => { await abrirPrimeraFila(page); await abrirPrimeraFila(page); },
  },

  // Las tres pestañas de la bandeja de aprobación: son las que cambiaron por
  // completo al añadir el saldo del solicitante y rediseñar la fila.
  // Pestaña "Mi Equipo" (directorio del personal a cargo): el aprobador del
  // seeder supervisa a tres empleados, así que salen las tres tarjetas.
  { archivo: '34_aprobador_mi_equipo.png', rol: 'aprobador', ruta: '/team-vacations?tab=roster', espera: 'Saldo de vacaciones' },
  { archivo: '23_admin_vacaciones_pendientes.png', rol: 'aprobador', ruta: '/team-vacations?tab=pending', espera: 'Solicitudes Pendientes' },
  { archivo: '24_admin_vacaciones_confirmar.png', rol: 'aprobador', ruta: '/team-vacations?tab=confirm' },
  { archivo: '25_admin_vacaciones_historial.png', rol: 'aprobador', ruta: '/team-vacations?tab=history' },
  { archivo: '26_admin_vacaciones_calendario.png', rol: 'admin', ruta: '/team-vacations?tab=calendar' },

  { archivo: '14_admin_vacation_history.png', rol: 'admin', ruta: '/vacation-history' },

  { archivo: '15_root_dashboard_top.png', rol: 'root', ruta: '/dashboard' },
  { archivo: '15_root_dashboard_bottom.png', rol: 'root', ruta: '/dashboard', mitad: 'abajo' },
  { archivo: '16_root_companies_list.png', rol: 'root', ruta: '/tenants' },
  { archivo: '17_root_company_form_top.png', rol: 'root', ruta: '/tenants/new', mitad: 'arriba' },
  { archivo: '17_root_company_form_bottom.png', rol: 'root', ruta: '/tenants/new', mitad: 'abajo' },
  { archivo: '18_root_users_list.png', rol: 'root', ruta: '/users' },
  { archivo: '19_root_user_form_top.png', rol: 'root', ruta: '/users/new', mitad: 'arriba' },
  { archivo: '20_root_user_form_bottom.png', rol: 'root', ruta: '/users/new', mitad: 'abajo' },

  /*
   * Admin Clientes: el rol que mas cambio con las observaciones del 04-08
   * (empresas en solo lectura, y ya no administra cuentas Admin) y del que el
   * manual no tenia ni una captura. El usuario estaba definido en USUARIOS
   * desde el principio, pero ningun escenario lo usaba.
   */
  { archivo: '31_admin_clientes_empresas.png', rol: 'adminTenant', ruta: '/tenants' },
  {
    archivo: '32_admin_clientes_empresa_detalle.png', rol: 'adminTenant', ruta: '/tenants',
    accion: (page) => abrirPrimeraFila(page),
  },
  { archivo: '33_admin_clientes_usuarios.png', rol: 'adminTenant', ruta: '/users' },

  /*
   * Capturas del modal de firma. Dejan la pagina en el visor con el PDF
   * cargado, un Dialog de Radix abierto y el temporizador de reenvio de codigo
   * corriendo; antes eso colgaba el cambio de rol siguiente y por eso iban las
   * ultimas. Ya no hace falta: cada rol abre un contexto de navegador nuevo.
   */
  {
    archivo: '04_signature_modal_step1.png', rol: 'empleado', ruta: '/dashboard',
    viewport: true,
    accion: async (page) => {
      // "Firmar" y no "Ver": el boton de firma solo aparece en las tarjetas
      // de documentos PENDIENTES, y lleva al visor de uno que si se puede
      // firmar. Entrando por "Ver" caiamos en el primero de la lista, que el
      // seeder deja ya firmado, y alli no hay boton que pulsar.
      await pulsarTexto(page, 'Firmar');
      await pulsarTexto(page, 'Firmar documento');
    },
  },
  {
    archivo: '05_signature_modal_step2.png', rol: 'empleado', ruta: '/dashboard',
    viewport: true,
    accion: async (page) => {
      await pulsarTexto(page, 'Firmar');
      await pulsarTexto(page, 'Firmar documento');
      // El modal arranca en "terms" si el empleado no ha aceptado todavia. Ese
      // paso puede no existir (de ahi el catch), pero cuando existe hay que
      // MARCAR LA CASILLA antes: sin ella "Aceptar y Continuar" esta
      // deshabilitado y el clic no hace nada, que es justo donde se quedaba.
      await marcarCasilla(page).catch(() => {});
      await pulsarTexto(page, 'Aceptar y Continuar').catch(() => {});
      await pulsarTexto(page, 'Enviar Código');
    },
  },
  // Pantallas de plataforma que el manual no documenta todavía.
  { archivo: '28_root_carga_masiva_usuarios.png', rol: 'root', ruta: '/users/batch-upload' },
  /*
   * Vista previa YA VALIDADA (el editor en cuadricula), sin confirmar la
   * carga: confirmar crearia usuarios de verdad y ensuciaria los listados que
   * se capturan despues. La validacion en si no persiste nada.
   */
  {
    archivo: '28b_root_carga_masiva_validada.png', rol: 'root', ruta: '/users/batch-upload',
    accion: async (page) => {
      const input = await page.waitForSelector('#file-input');
      await input.uploadFile(path.resolve(__dirname, '../example/usuarios.xlsx'));
      await pulsarTexto(page, 'Validar y Editar');
      await page.waitForFunction(
        () => document.body.innerText.includes('Editor de Usuarios'),
        { timeout: 15000 },
      );
    },
  },
  // Pestaña "Empresas" (la que abre por defecto): certificados por empresa.
  { archivo: '29_root_ajustes_firma.png', rol: 'root', ruta: '/signature-settings', espera: 'Certificados por empresa' },
  // Pestaña "Certificado global".
  {
    archivo: '35_root_firma_certificado_global.png', rol: 'root', ruta: '/signature-settings?tab=global',
    espera: 'Certificado global de la plataforma',
  },
  { archivo: '30_root_ajustes_plataforma.png', rol: 'root', ruta: '/platform-settings' },

  /*
   * "Iniciar sesión como este usuario": LA ÚLTIMA a propósito. Entrar como
   * otro usuario recarga la app con la identidad del empleado, deja una cookie
   * de "boleto de vuelta" del root y escribe en la auditoría. Como es la
   * última, el navegador se cierra a continuación y nada posterior hereda ese
   * estado. Se pulsa el primer botón de entrada de la lista (el botón no se
   * ofrece sobre root ni sobre uno mismo, así que cualquier fila vale) y se
   * espera a la franja ámbar.
   */
  {
    archivo: '37_root_entrar_como_usuario.png', rol: 'root', ruta: '/users',
    accion: async (page) => {
      await page.waitForSelector('button[title="Iniciar sesión como este usuario"]', { timeout: 15000 });
      await page.click('button[title="Iniciar sesión como este usuario"]');
      await page.waitForFunction(
        () => document.body.innerText.includes('Estás viendo la aplicación como'),
        { timeout: 20000 },
      );
    },
  },
];

/*
 * Pulsa el primer elemento cuyo texto visible contenga `texto`. Se busca por
 * texto y no por clases de Tailwind porque estas cambian con cualquier retoque
 * de estilo, mientras que el texto es justo lo que el manual describe.
 */
async function pulsarTexto(page, texto, selector = 'button, a, [role="button"]') {
  // Se ESPERA a que el boton exista antes de pulsarlo. Sin esta espera, los
  // pasos encadenados de un modal fallaban: entre aceptar los terminos y que
  // aparezca "Enviar Código" hay una llamada al backend, y el clic salia antes
  // de que el boton estuviera pintado.
  await page.waitForFunction(
    (t, sel) => Array.from(document.querySelectorAll(sel))
      .some((e) => e.innerText && e.innerText.trim().includes(t)),
    { timeout: 10000 },
    texto,
    selector,
  ).catch(() => { throw new Error(`No hay nada que pulsar con el texto "${texto}"`); });

  await page.evaluate((t, sel) => {
    Array.from(document.querySelectorAll(sel))
      .find((e) => e.innerText && e.innerText.trim().includes(t))
      .click();
  }, texto, selector);

  await new Promise((r) => setTimeout(r, 1200));
}

/*
 * Como pulsarTexto, pero exige que el texto del elemento sea EXACTAMENTE
 * `texto` (tras trim), no que lo contenga. Necesario cuando existe un botón
 * con texto más largo que incluye al que se busca como substring: en el
 * dashboard del empleado, el banner "Ver Documentos Pendientes" aparece antes
 * en el DOM que el "Ver" de cada fila, así que pulsarTexto(page, 'Ver')
 * siempre acababa pulsando el banner en vez de abrir el visor.
 */
async function pulsarTextoExacto(page, texto, selector = 'button, a, [role="button"]') {
  await page.waitForFunction(
    (t, sel) => Array.from(document.querySelectorAll(sel))
      .some((e) => e.innerText && e.innerText.trim() === t),
    { timeout: 10000 },
    texto,
    selector,
  ).catch(() => { throw new Error(`No hay nada que pulsar con el texto exacto "${texto}"`); });

  await page.evaluate((t, sel) => {
    Array.from(document.querySelectorAll(sel))
      .find((e) => e.innerText && e.innerText.trim() === t)
      .click();
  }, texto, selector);

  await new Promise((r) => setTimeout(r, 1200));
}

/*
 * Marca la casilla de un modal abierto. Los Checkbox de Radix se pintan como
 * <button role="checkbox">, no como <input>, asi que no valen ni page.check ni
 * un selector de input.
 */
async function marcarCasilla(page) {
  const marcada = await page.evaluate(() => {
    const casilla = document.querySelector('[role="dialog"] [role="checkbox"]');
    if (!casilla) return false;
    casilla.click();
    return true;
  });

  if (!marcada) throw new Error('No hay ninguna casilla que marcar en el modal');
  await new Promise((r) => setTimeout(r, 400));
}

/*
 * Abre la primera fila de la tabla en pantalla. Lo usan las capturas que no
 * tienen URL propia y a las que solo se llega pulsando: el detalle de un lote,
 * el visor de un documento o el detalle de una empresa. Se pulsa la primera
 * fila y no un id fijo porque los ids del seeder cambian en cada instalacion.
 */
async function abrirPrimeraFila(page) {
  const abierto = await page.evaluate(() => {
    const fila = document.querySelector('tbody tr');
    if (!fila) return false;
    (fila.querySelector('button, a') || fila).click();
    return true;
  });

  if (!abierto) throw new Error('La tabla no tiene filas: ¿el seeder dejó datos?');
  await new Promise((r) => setTimeout(r, 1500));
}

async function iniciarSesion(page, email) {
  // 'networkidle2' es OBLIGATORIO aqui, aunque sea mas lento: al montar, la
  // pantalla pide /sanctum/csrf-cookie, y si se envia el formulario antes de
  // que llegue, el backend responde 419 y el login falla en silencio. Se probo
  // con 'domcontentloaded' y fallaron TODOS los inicios de sesion menos el
  // primero.
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle2' });

  // El campo es #login (type="text"), no un input de correo: el sistema acepta
  // DNI O correo. Se usa el correo por ser más legible en el manual. Los
  // selectores van por id y no por clases de Tailwind, que cambian con
  // cualquier retoque de estilo.
  await page.waitForSelector('#login', { timeout: 15000 });

  const campoUsuario = await page.$('#login');
  await campoUsuario.click({ clickCount: 3 });
  await campoUsuario.type(email);

  const campoClave = await page.$('#password');
  await campoClave.click({ clickCount: 3 });
  await campoClave.type(CLAVE);

  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 20000 }).catch(() => {}),
    page.click('button[type="submit"]'),
  ]);

  // La SPA puede no disparar navegación: se espera a salir de /login.
  await page.waitForFunction(() => !window.location.pathname.includes('/login'), { timeout: 20000 })
    .catch(() => { throw new Error(`No se pudo iniciar sesión como ${email}`); });
}

(async () => {
  if (!fs.existsSync(SALIDA)) fs.mkdirSync(SALIDA, { recursive: true });

  const pendientes = CAPTURAS.filter((c) => !FILTRO || c.archivo.includes(FILTRO));
  if (!pendientes.length) {
    console.error(`Ninguna captura casa con "${FILTRO}"`);
    process.exit(1);
  }

  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });

  // Un contexto de navegador NUEVO por rol, en vez de reutilizar la pestaña y
  // limpiar localStorage: tras capturar el visor, PDF.js deja el hilo principal
  // ocupado y hasta el goto/evaluate para limpiar la sesión se quedaba colgado
  // sin timeout. Cerrar el contexto mata la pestaña sin pedirle nada a la página.
  let contexto = null;
  let page = null;
  async function nuevaSesion() {
    if (contexto) await contexto.close().catch(() => {});
    contexto = await browser.createBrowserContext();
    page = await contexto.newPage();
    await page.setViewport(VIEWPORT);
  }
  await nuevaSesion();

  let rolActual = undefined;
  let ok = 0;
  const fallos = [];

  for (const captura of pendientes) {
    try {
      if (captura.rol !== rolActual) {
        await nuevaSesion();
        if (captura.rol) await iniciarSesion(page, USUARIOS[captura.rol]);
        rolActual = captura.rol;
      }

      await page.goto(`${BASE_URL}${captura.ruta}`, { waitUntil: 'networkidle2', timeout: 20000 });

      if (captura.espera) {
        await page.waitForFunction(
          (t) => document.body.innerText.includes(t),
          { timeout: 15000 },
          captura.espera,
        );
      }

      // `accion` permite llegar a pantallas que no tienen URL propia: modales,
      // el visor de un documento o el detalle de un lote, a los que solo se
      // entra pulsando. Antes esas catorce capturas se hacian a mano, y por eso
      // eran las que llevaban meses sin actualizarse.
      if (captura.accion) await captura.accion(page);

      // Margen para que terminen las animaciones de entrada; si no, se capturan
      // tarjetas a media opacidad.
      await new Promise((r) => setTimeout(r, 700));

      // Sin transiciones durante la captura: fullPage redimensiona la ventana
      // y el sidebar (transition-all 300ms) salía a medio deslizar, con el
      // menú fuera de cuadro.
      await page.addStyleTag({
        content: '*, *::before, *::after { transition: none !important; animation: none !important; }',
      });

      // Los gráficos (Recharts) salían en blanco: sus datos llegan después del
      // networkidle2 y además animan por JS, no por CSS. Se espera a que haya
      // barras o sectores pintados y luego a que termine la animación.
      if (await page.$('.recharts-wrapper')) {
        // Solo se dibujan al entrar en pantalla: se baja hasta el final para
        // dispararlos y se vuelve arriba.
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await new Promise((r) => setTimeout(r, 800));
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.waitForFunction(
          () => document.querySelectorAll('.recharts-sector, .recharts-bar-rectangle').length > 0,
          { timeout: 15000 },
        ).catch(() => {});
        await new Promise((r) => setTimeout(r, 2000));
      }

      const destino = path.join(SALIDA, captura.archivo);

      // `mitad` captura solo el viewport, arriba o abajo del todo, para las
      // paginas que el manual parte en dos (_top / _bottom). Con fullPage las
      // dos mitades salian identicas: la pagina entera dos veces.
      //
      // `viewport` es lo mismo sin desplazarse, y es OBLIGATORIO con un modal
      // abierto: los Dialog de Radix bloquean el scroll del body, y fullPage
      // sobre eso deja a Puppeteer colgado indefinidamente — se comio once
      // minutos antes de que hubiera que matarlo.
      if (captura.mitad || captura.viewport) {
        if (captura.mitad) {
          await page.evaluate((m) => {
            window.scrollTo(0, m === 'abajo' ? document.body.scrollHeight : 0);
          }, captura.mitad);
          await new Promise((r) => setTimeout(r, 400));
        }
        await page.screenshot({ path: destino, fullPage: false });
      } else {
        // Volver ARRIBA antes de la captura a pagina completa. La barra
        // superior y el menu lateral son `position: fixed`, asi que se pintan
        // donde este el scroll: si la `accion` desplazo la pagina (abrir el
        // selector de periodo, por ejemplo), salen flotando a media captura en
        // vez de en su sitio. Le paso justo con 09_admin_upload_filled.
        await page.evaluate(() => window.scrollTo(0, 0));
        await new Promise((r) => setTimeout(r, 400));
        // Ventana del alto de la página ANTES de capturar, en vez de fullPage:
        // fullPage redimensiona en el momento del disparo, los gráficos
        // (ResponsiveContainer) se vuelven a animar desde cero y salían vacíos.
        const alto = await page.evaluate(() => document.documentElement.scrollHeight);
        await page.setViewport({ ...VIEWPORT, height: Math.max(alto, VIEWPORT.height) });
        const conGraficos = Boolean(await page.$('.recharts-wrapper'));
        await new Promise((r) => setTimeout(r, conGraficos ? 2500 : 400));
        await page.screenshot({ path: destino, fullPage: false });
        await page.setViewport(VIEWPORT);
      }

      // Un modal abierto deja el <body> con el scroll bloqueado por Radix, y
      // la navegacion de la captura SIGUIENTE se queda colgada para siempre.
      // Se cierra con Escape antes de continuar: sin esto, la tanda completa
      // moria justo despues de las dos capturas de firma.
      if (captura.viewport) {
        await page.keyboard.press('Escape');
        await new Promise((r) => setTimeout(r, 400));
      }
      console.log(`  ok   ${captura.archivo}`);
      ok++;
    } catch (e) {
      console.log(`  FALLA ${captura.archivo}: ${e.message.split('\n')[0]}`);
      fallos.push(captura.archivo);
      rolActual = undefined; // fuerza re-login en la siguiente
    }
  }

  await browser.close();

  console.log(`\n${ok}/${pendientes.length} capturas generadas en docs/images/`);
  if (fallos.length) {
    console.log(`Fallaron: ${fallos.join(', ')}`);
    process.exit(1);
  }
})();
