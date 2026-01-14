#!/usr/bin/env node

/**
 * Generate cover images for Word documents
 * Uses Puppeteer to render the same cover as PDF
 */

const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

const CONFIG = {
    docsPath: path.join(__dirname, '..'),
    version: '1.0.0',
    date: 'Enero 2026',
    documents: [
        {
            id: 'manual',
            title: 'Manual de Usuario',
            subtitle: 'Guía de uso del sistema para usuarios finales y administradores',
            audience: 'Usuarios, Administradores'
        },
        {
            id: 'tecnico',
            title: 'Documentación Técnica',
            subtitle: 'Arquitectura, configuración y despliegue del sistema',
            audience: 'Desarrolladores, DevOps'
        }
    ]
};

function generateCoverHTML(doc) {
    return `
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
    
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
      width: 816px;
      height: 1056px;
      font-family: 'Inter', -apple-system, sans-serif;
    }
    
    .cover-page {
      width: 100%;
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
      background: linear-gradient(135deg, #2563eb 0%, #3b82f6 50%, #60a5fa 100%);
      color: white;
      padding: 4rem 3rem;
    }
    
    .cover-logo {
      width: 100px;
      height: 100px;
      background: rgba(255, 255, 255, 0.2);
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 2rem;
      border: 2px solid rgba(255, 255, 255, 0.3);
    }
    
    .cover-logo svg {
      width: 56px;
      height: 56px;
    }
    
    .cover-title {
      font-size: 3rem;
      font-weight: 800;
      margin-bottom: 0.5rem;
      letter-spacing: -0.02em;
    }
    
    .cover-subtitle {
      font-size: 1.3rem;
      font-weight: 400;
      opacity: 0.9;
      margin-bottom: 3rem;
    }
    
    .cover-description {
      font-size: 1rem;
      font-weight: 300;
      opacity: 0.85;
      max-width: 400px;
      line-height: 1.5;
    }
    
    .cover-meta {
      display: flex;
      gap: 3rem;
      margin-top: 3rem;
    }
    
    .cover-meta-item {
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    
    .cover-meta-label {
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      opacity: 0.7;
      margin-bottom: 0.25rem;
    }
    
    .cover-meta-value {
      font-size: 1rem;
      font-weight: 600;
    }
    
    .cover-footer {
      margin-top: 4rem;
      font-size: 0.85rem;
      opacity: 0.7;
    }
  </style>
</head>
<body>
  <div class="cover-page">
    <div class="cover-logo">
      <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="8" y="12" width="48" height="40" rx="4" stroke="white" stroke-width="3"/>
        <path d="M16 24h32M16 32h24M16 40h28" stroke="white" stroke-width="2" stroke-linecap="round"/>
        <circle cx="48" cy="44" r="12" fill="white" fill-opacity="0.2"/>
        <path d="M44 44l3 3 6-6" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
    
    <h1 class="cover-title">MiBoleta</h1>
    <p class="cover-subtitle">${doc.title}</p>
    <p class="cover-description">${doc.subtitle}</p>
    
    <div class="cover-meta">
      <div class="cover-meta-item">
        <span class="cover-meta-label">Versión</span>
        <span class="cover-meta-value">${CONFIG.version}</span>
      </div>
      <div class="cover-meta-item">
        <span class="cover-meta-label">Fecha</span>
        <span class="cover-meta-value">${CONFIG.date}</span>
      </div>
      <div class="cover-meta-item">
        <span class="cover-meta-label">Audiencia</span>
        <span class="cover-meta-value">${doc.audience}</span>
      </div>
    </div>
    
    <p class="cover-footer">Sistema de Gestión Documental y Vacaciones</p>
  </div>
</body>
</html>
  `;
}

async function generateCovers() {
    console.log('🚀 Generating cover images...\n');

    const browser = await puppeteer.launch({
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    for (const doc of CONFIG.documents) {
        console.log(`📝 Creating cover for: ${doc.title}`);

        const page = await browser.newPage();
        await page.setViewport({ width: 816, height: 1056 });

        const html = generateCoverHTML(doc);
        await page.setContent(html, { waitUntil: 'networkidle0' });
        await page.evaluate(() => document.fonts.ready);

        const coverPath = path.join(__dirname, `cover-${doc.id}.png`);
        await page.screenshot({ path: coverPath, fullPage: true });

        console.log(`  ✅ Generated: cover-${doc.id}.png`);
        await page.close();
    }

    await browser.close();
    console.log('\n✅ All cover images generated!');
}

generateCovers().catch(err => {
    console.error('❌ Error:', err);
    process.exit(1);
});
