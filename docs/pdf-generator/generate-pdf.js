#!/usr/bin/env node

/**
 * MiBoleta Documentation PDF Generator
 * 
 * Generates two separate professional PDFs from Markdown documentation files:
 * - MiBoleta-Documentacion-Tecnica.pdf
 * - MiBoleta-Documentacion-Funcional.pdf
 * 
 * Features:
 * - Professional cover page
 * - Table of contents with links
 * - Mermaid diagrams rendered as images
 * 
 * Usage: node generate-pdf.js
 * 
 * Requirements: npm install puppeteer marked
 */

const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');
const { marked } = require('marked');

// Configuration
const CONFIG = {
  docsPath: path.join(__dirname, '..'),
  // Versión y fecha desde version.js: estaban repetidas en los tres
  // generadores y la portada acababa contradiciendo a la primera página.
  ...require('./version'),
  documents: [
    {
      input: 'USER-MANUAL.md',
      output: 'MiBoleta-Manual-de-Usuario.pdf',
      title: 'Manual de Usuario',
      subtitle: 'Guía de uso del sistema para usuarios finales y administradores',
      icon: '📘',
      audience: 'Usuarios, Administradores'
    },
    {
      input: 'TECHNICAL-DOCUMENTATION.md',
      output: 'MiBoleta-Documentacion-Tecnica.pdf',
      title: 'Documentación Técnica',
      subtitle: 'Arquitectura, configuración y despliegue del sistema',
      icon: '⚙️',
      audience: 'Desarrolladores, DevOps'
    },
    // La funcional no estaba en la tubería, así que su PDF se quedó congelado
    // en enero mientras el .md se actualizaba: el mismo documento decía dos
    // cosas distintas según se leyera en PDF o en texto.
    {
      input: 'FUNCTIONAL-DOCUMENTATION.md',
      output: 'MiBoleta-Documentacion-Funcional.pdf',
      title: 'Documentación Funcional',
      subtitle: 'Módulos, roles y flujos del sistema',
      icon: '📋',
      audience: 'Funcional, Producto, QA'
    }
  ]
};

// Function to generate slug from text
function slugify(text) {
  return text
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^\w\s-]/g, '')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-')
    .trim();
}

// Counter for unique mermaid IDs
let mermaidCounter = 0;

// Custom Markdown renderer
function createRenderer() {
  const renderer = new marked.Renderer();

  renderer.heading = function (text, level) {
    const id = slugify(text);
    return `<h${level} id="${id}">${text}</h${level}>`;
  };

  // Handle code blocks - Mermaid diagrams will be rendered client-side
  renderer.code = function (code, language) {
    if (language === 'mermaid') {
      mermaidCounter++;
      // Return a pre tag with mermaid class that will be rendered by mermaid.js
      return `<div class="mermaid-container no-break">
        <pre class="mermaid">${escapeHtml(code)}</pre>
      </div>`;
    }
    const langClass = language ? ` class="language-${language}"` : '';
    return `<pre class="no-break"><code${langClass}>${escapeHtml(code)}</code></pre>`;
  };

  renderer.table = function (header, body) {
    return `<table class="no-break">
      <thead>${header}</thead>
      <tbody>${body}</tbody>
    </table>`;
  };

  renderer.image = function (href, title, text) {
    const imagePath = path.join(CONFIG.docsPath, href);
    if (fs.existsSync(imagePath)) {
      const imageData = fs.readFileSync(imagePath);
      const base64 = imageData.toString('base64');
      const ext = path.extname(href).slice(1);
      return `<img src="data:image/${ext};base64,${base64}" alt="${text}" title="${title || text}" />`;
    }
    return `<div class="image-placeholder no-break">
      <p>📷 <strong>${text}</strong></p>
      <p><em>Imagen: ${href}</em></p>
    </div>`;
  };

  return renderer;
}

function escapeHtml(text) {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function extractTOC(markdown) {
  const headings = [];
  const regex = /^(#{1,3})\s+(.+)$/gm;
  let match;

  while ((match = regex.exec(markdown)) !== null) {
    const level = match[1].length;
    const text = match[2].trim();
    const id = slugify(text);
    headings.push({ level, text, id });
  }

  return headings;
}

function generateCoverPage(doc) {
  return `
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
  `;
}

function generateTOCPage(toc) {
  const renderItems = (items) => {
    return items.map(item => {
      const indent = item.level > 1 ? 'toc-subitem' : '';
      return `<li class="toc-item ${indent}">
        <a href="#${item.id}">${item.text}</a>
      </li>`;
    }).join('\n');
  };

  return `
    <div class="toc-page">
      <h1 class="toc-title">📑 Tabla de Contenidos</h1>
      <ul class="toc-list">
        ${renderItems(toc.filter(h => h.level <= 2))}
      </ul>
    </div>
  `;
}

function processMarkdown(content) {
  mermaidCounter = 0; // Reset counter for each document
  const renderer = createRenderer();
  marked.setOptions({
    renderer: renderer,
    gfm: true,
    breaks: false,
    headerIds: false,
    mangle: false
  });

  return marked.parse(content);
}

async function generateSinglePDF(browser, doc, css) {
  const inputPath = path.join(CONFIG.docsPath, doc.input);

  if (!fs.existsSync(inputPath)) {
    console.error(`  ❌ File not found: ${inputPath}`);
    return false;
  }

  const markdown = fs.readFileSync(inputPath, 'utf-8');
  const toc = extractTOC(markdown);

  console.log(`  📋 Found ${toc.length} headings`);

  const processedContent = processMarkdown(markdown);
  console.log(`  📊 Found ${mermaidCounter} Mermaid diagrams`);

  // Generate HTML with Mermaid.js included
  const html = `
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MiBoleta - ${doc.title}</title>
  <style>${css}</style>
  <!-- Mermaid.js for diagram rendering -->
  <script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
  <script>
    mermaid.initialize({
      startOnLoad: true,
      theme: 'default',
      themeVariables: {
        // Node colors - light blue background
        primaryColor: '#dbeafe',
        primaryTextColor: '#1e40af',
        primaryBorderColor: '#3b82f6',
        
        // Secondary nodes
        secondaryColor: '#e0f2fe',
        secondaryTextColor: '#0369a1',
        secondaryBorderColor: '#0ea5e9',
        
        // Tertiary nodes
        tertiaryColor: '#f0fdf4',
        tertiaryTextColor: '#166534',
        tertiaryBorderColor: '#22c55e',
        
        // Lines and arrows
        lineColor: '#64748b',
        arrowheadColor: '#64748b',
        
        // Background
        background: '#ffffff',
        mainBkg: '#dbeafe',
        nodeBkg: '#dbeafe',
        clusterBkg: '#f8fafc',
        
        // Text
        textColor: '#1e293b',
        nodeTextColor: '#1e40af',
        
        // Fonts
        fontFamily: 'Inter, -apple-system, sans-serif',
        fontSize: '14px'
      },
      flowchart: {
        htmlLabels: true,
        curve: 'basis',
        padding: 8,
        nodeSpacing: 30,
        rankSpacing: 30,
        useMaxWidth: false,
        diagramPadding: 8
      },
      sequence: {
        actorMargin: 50,
        boxMargin: 10,
        mirrorActors: false,
        bottomMarginAdj: 10,
        useMaxWidth: true,
        actorFontFamily: 'Inter, sans-serif',
        noteFontFamily: 'Inter, sans-serif',
        messageFontFamily: 'Inter, sans-serif'
      }
    });
  </script>
</head>
<body>
  ${generateCoverPage(doc)}
  
  <div class="page-break-before">
    ${generateTOCPage(toc)}
  </div>
  
  <div class="document-section page-break-before">
    ${processedContent}
  </div>
</body>
</html>
  `;

  // Save HTML for preview
  const previewName = doc.output.replace('.pdf', '.html');
  const htmlPath = path.join(__dirname, previewName);
  fs.writeFileSync(htmlPath, html);
  console.log(`  📄 Preview: ${previewName}`);

  // Generate PDF
  const page = await browser.newPage();

  // Set content and wait for everything to load
  await page.setContent(html, { waitUntil: 'networkidle0' });

  // Wait for fonts
  await page.evaluate(() => document.fonts.ready);

  // Wait for Mermaid to render all diagrams
  if (mermaidCounter > 0) {
    console.log(`  ⏳ Waiting for Mermaid diagrams to render...`);
    await page.waitForFunction(() => {
      const diagrams = document.querySelectorAll('.mermaid');
      return Array.from(diagrams).every(d => d.querySelector('svg') !== null);
    }, { timeout: 30000 }).catch(() => {
      console.log(`  ⚠️ Some diagrams may not have rendered`);
    });

    // Small delay to ensure SVGs are fully rendered
    await new Promise(resolve => setTimeout(resolve, 1000));
  }

  const pdfPath = path.join(CONFIG.docsPath, doc.output);
  await page.pdf({
    path: pdfPath,
    format: 'A4',
    printBackground: true,
    margin: {
      top: '1.5cm',
      right: '1.5cm',
      bottom: '2cm',
      left: '1.5cm'
    },
    displayHeaderFooter: true,
    headerTemplate: '<div></div>',
    footerTemplate: `
      <div style="width: 100%; font-size: 9px; padding: 0 1.5cm; display: flex; justify-content: space-between; color: #94a3b8;">
        <span>MiBoleta - ${doc.title} v${CONFIG.version}</span>
        <span>Página <span class="pageNumber"></span> de <span class="totalPages"></span></span>
      </div>
    `
  });

  await page.close();

  const size = (fs.statSync(pdfPath).size / 1024 / 1024).toFixed(2);
  console.log(`  ✅ Generated: ${doc.output} (${size} MB)`);

  return true;
}

async function generatePDFs() {
  console.log('🚀 Starting PDF generation...\n');

  // Read CSS
  const cssPath = path.join(__dirname, 'styles.css');
  const css = fs.readFileSync(cssPath, 'utf-8');

  // Launch browser once
  console.log('🔄 Launching browser...\n');
  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });

  // Generate each PDF
  for (const doc of CONFIG.documents) {
    console.log(`📝 Processing: ${doc.title}`);
    await generateSinglePDF(browser, doc, css);
    console.log('');
  }

  await browser.close();

  console.log('═'.repeat(50));
  console.log('✅ All PDFs generated successfully!');
  console.log('═'.repeat(50));
  console.log('\nOutput files:');
  CONFIG.documents.forEach(doc => {
    console.log(`  📁 ${path.join(CONFIG.docsPath, doc.output)}`);
  });
}

// Run
generatePDFs().catch(err => {
  console.error('❌ Error generating PDF:', err);
  process.exit(1);
});
