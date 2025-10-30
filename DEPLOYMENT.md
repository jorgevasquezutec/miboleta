# Guía de Deployment a GitHub Pages

Este documento describe cómo desplegar la aplicación MiBoleta en GitHub Pages.

## Métodos de Deployment

### Opción 1: Deployment Automático con GitHub Actions (Recomendado)

El proyecto está configurado para deployarse automáticamente cada vez que se hace push a la rama `main`.

#### Configuración Inicial

1. **Habilitar GitHub Pages en tu repositorio:**
   - Ve a `Settings` > `Pages` en tu repositorio de GitHub
   - En `Source`, selecciona `GitHub Actions`
   - Guarda los cambios

2. **Hacer push al repositorio:**
   ```bash
   git add .
   git commit -m "Configure GitHub Pages deployment"
   git push origin main
   ```

3. **Verificar el deployment:**
   - Ve a la pestaña `Actions` en tu repositorio
   - Verifica que el workflow "Deploy to GitHub Pages" se ejecute correctamente
   - Una vez completado, tu sitio estará disponible en: `https://<tu-usuario>.github.io/miboleta/`

#### Workflow

El archivo [.github/workflows/deploy.yml](.github/workflows/deploy.yml) contiene la configuración del workflow que:
- Se ejecuta automáticamente en cada push a `main`
- Instala dependencias con Node.js 20
- Construye la aplicación con `npm run build`
- Despliega el contenido de la carpeta `dist` a GitHub Pages

### Opción 2: Deployment Manual con gh-pages

Si prefieres controlar manualmente cuándo se despliega la aplicación:

1. **Construir y desplegar:**
   ```bash
   npm run deploy
   ```

   Este comando ejecutará:
   - `npm run build` - Construye la aplicación
   - `gh-pages -d dist` - Despliega la carpeta `dist` a la rama `gh-pages`

2. **Configurar GitHub Pages (solo la primera vez):**
   - Ve a `Settings` > `Pages` en tu repositorio
   - En `Source`, selecciona `Deploy from a branch`
   - En `Branch`, selecciona `gh-pages` y la carpeta `/ (root)`
   - Guarda los cambios

3. **Verificar el deployment:**
   - Espera unos minutos para que GitHub Pages procese el deployment
   - Tu sitio estará disponible en: `https://<tu-usuario>.github.io/miboleta/`

## Configuración del Proyecto

### vite.config.ts

El archivo está configurado con el base path correcto para GitHub Pages:

```typescript
export default defineConfig({
  base: '/miboleta/', // Nombre del repositorio
  // ...
  build: {
    target: 'esnext',
    outDir: 'dist', // Directorio de salida
  },
});
```

**Importante:** Si el nombre de tu repositorio es diferente a `miboleta`, debes actualizar el valor de `base` en [vite.config.ts](vite.config.ts#L7).

### package.json

Scripts configurados para deployment:

```json
{
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "predeploy": "npm run build",
    "deploy": "gh-pages -d dist"
  }
}
```

## Desarrollo Local

Para probar la aplicación localmente:

```bash
npm run dev
```

La aplicación estará disponible en `http://localhost:3000`

## Build de Producción

Para construir la aplicación para producción sin desplegar:

```bash
npm run build
```

Los archivos se generarán en la carpeta `dist/`.

## Troubleshooting

### El sitio no carga correctamente

- Verifica que el `base` en `vite.config.ts` coincida exactamente con el nombre de tu repositorio
- Asegúrate de que GitHub Pages esté habilitado en la configuración del repositorio
- Revisa la pestaña `Actions` para ver si hay errores en el workflow

### Error 404 al navegar a rutas

GitHub Pages no soporta routing del lado del cliente por defecto. Para solucionar esto:

1. Considera usar hash routing en lugar de browser routing
2. O implementa una solución con el archivo `404.html` que redirija al `index.html`

### El workflow falla en GitHub Actions

- Verifica que los permisos estén configurados correctamente en el workflow
- Asegúrate de que `GitHub Actions` esté seleccionado como source en la configuración de Pages
- Revisa los logs del workflow para identificar el error específico

## URLs Importantes

- **Producción:** `https://<tu-usuario>.github.io/miboleta/`
- **Repositorio:** `https://github.com/<tu-usuario>/miboleta`
- **GitHub Actions:** `https://github.com/<tu-usuario>/miboleta/actions`
- **Configuración de Pages:** `https://github.com/<tu-usuario>/miboleta/settings/pages`

## Notas Adicionales

- Los deployments automáticos solo ocurren en pushes a la rama `main`
- Puedes disparar un deployment manual desde la pestaña `Actions` usando el botón "Run workflow"
- El tiempo de deployment típico es de 2-5 minutos
- Los cambios pueden tardar unos minutos adicionales en propagarse en el CDN de GitHub
