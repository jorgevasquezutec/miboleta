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
 * Uso:
 *   node docs/screenshots/capture.js                 # todas
 *   node docs/screenshots/capture.js vacaciones      # solo las que casen
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
  { archivo: '03_client_mis_documentos.png', rol: 'empleado', ruta: '/documents' },
  { archivo: '05_client_vacaciones.png', rol: 'empleado', ruta: '/vacations' },
  { archivo: '07_vacation_request_form.png', rol: 'empleado', ruta: '/vacations/new' },
  { archivo: '27_perfil.png', rol: 'empleado', ruta: '/profile' },

  { archivo: '08_admin_dashboard.png', rol: 'admin', ruta: '/dashboard' },
  { archivo: '10_admin_users.png', rol: 'admin', ruta: '/users' },
  { archivo: '11_admin_documents.png', rol: 'admin', ruta: '/documents' },
  { archivo: '12_admin_audit.png', rol: 'admin', ruta: '/audit-logs' },
  { archivo: '21_admin_batch_list.png', rol: 'admin', ruta: '/batches' },

  // Las tres pestañas de la bandeja de aprobación: son las que cambiaron por
  // completo al añadir el saldo del solicitante y rediseñar la fila.
  { archivo: '23_admin_vacaciones_pendientes.png', rol: 'aprobador', ruta: '/team-vacations?tab=pending', espera: 'Solicitudes Pendientes' },
  { archivo: '24_admin_vacaciones_confirmar.png', rol: 'aprobador', ruta: '/team-vacations?tab=confirm' },
  { archivo: '25_admin_vacaciones_historial.png', rol: 'aprobador', ruta: '/team-vacations?tab=history' },
  { archivo: '26_admin_vacaciones_calendario.png', rol: 'admin', ruta: '/team-vacations?tab=calendar' },

  { archivo: '14_admin_vacation_history.png', rol: 'admin', ruta: '/vacation-history' },

  { archivo: '15_root_dashboard_top.png', rol: 'root', ruta: '/dashboard' },
  { archivo: '16_root_companies_list.png', rol: 'root', ruta: '/tenants' },
  { archivo: '18_root_users_list.png', rol: 'root', ruta: '/users' },
  // Pantallas de plataforma que el manual no documenta todavía.
  { archivo: '28_root_carga_masiva_usuarios.png', rol: 'root', ruta: '/users/batch-upload' },
  { archivo: '29_root_ajustes_firma.png', rol: 'root', ruta: '/signature-settings' },
  { archivo: '30_root_ajustes_plataforma.png', rol: 'root', ruta: '/platform-settings' },
];

async function iniciarSesion(page, email) {
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

async function cerrarSesion(page) {
  // En about:blank (antes de la primera navegación) el navegador prohíbe el
  // acceso a localStorage: hay que estar en el origen de la app para poder
  // limpiarlo.
  if (!page.url().startsWith(BASE_URL)) return;

  // Solo localStorage: ahí vive la sesión (auth-storage). Las cookies NO se
  // tocan — borrarlas tumba el token CSRF de Sanctum y el siguiente login
  // falla con 419, que es exactamente lo que pasaba: la primera sesión
  // funcionaba y todos los cambios de rol posteriores no.
  await page.evaluate(() => { localStorage.clear(); sessionStorage.clear(); });
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

  const page = await browser.newPage();
  await page.setViewport(VIEWPORT);

  let rolActual = undefined;
  let ok = 0;
  const fallos = [];

  for (const captura of pendientes) {
    try {
      if (captura.rol !== rolActual) {
        await cerrarSesion(page);
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

      // Margen para que terminen las animaciones de entrada; si no, se capturan
      // tarjetas a media opacidad.
      await new Promise((r) => setTimeout(r, 700));

      const destino = path.join(SALIDA, captura.archivo);
      await page.screenshot({ path: destino, fullPage: true });
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
