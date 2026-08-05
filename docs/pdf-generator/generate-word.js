#!/usr/bin/env node

/**
 * MiBoleta Documentation Word Generator
 * 
 * Generates Word documents with native formatting (not HTML conversion).
 * Uses the 'docx' library for proper Word document creation.
 * 
 * Usage: node generate-word.js
 */

const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');
const { marked } = require('marked');
const {
  Document,
  Packer,
  Paragraph,
  TextRun,
  HeadingLevel,
  Table,
  TableRow,
  TableCell,
  WidthType,
  AlignmentType,
  ShadingType,
  BorderStyle,
  ImageRun,
  PageBreak,
  TableOfContents,
  Header,
  Footer,
  PageNumber,
  NumberFormat,
  convertInchesToTwip,
  ExternalHyperlink,
  Bookmark,
  InternalHyperlink
} = require('docx');

// Configuration
const CONFIG = {
  // De DONDE se leen las fuentes: los .md y las capturas.
  docsPath: path.join(__dirname, '..'),
  // A DONDE salen los .docx: la misma carpeta que los PDF. Antes caían en
  // docs/, junto a los .md de los que salen, así que los tres documentos de la
  // entrega vivían en dos sitios distintos según su formato y los scripts de
  // empaquetado solo sabían del de los PDF.
  salidaPath: path.join(__dirname, '..', '..', 'dist', 'documentacion'),
  // Ver nota en version.js: fuente única de versión y fecha.
  ...require('./version'),
  // Los mismos tres documentos que genera generate-pdf.js: lo que se entrega
  // va en ambos formatos, sin excepciones. `cover` nombra el PNG de portada
  // (generate-covers.js); antes se deducía del nombre del archivo con un
  // includes('Manual'), que daba 'tecnico' a cualquier documento nuevo.
  documents: [
    {
      input: 'USER-MANUAL.md',
      output: 'MiBoleta-Manual-de-Usuario.docx',
      cover: 'manual',
      title: 'Manual de Usuario',
      subtitle: 'Guía de uso del sistema para usuarios finales y administradores',
      audience: 'Usuarios, Administradores'
    },
    {
      input: 'TECHNICAL-DOCUMENTATION.md',
      output: 'MiBoleta-Documentacion-Tecnica.docx',
      cover: 'tecnico',
      title: 'Documentación Técnica',
      subtitle: 'Arquitectura, configuración y despliegue del sistema',
      audience: 'Desarrolladores, DevOps'
    },
    {
      input: 'INSTALACION.md',
      output: 'MiBoleta-Guia-de-Instalacion.docx',
      cover: 'instalacion',
      title: 'Guía de Instalación',
      subtitle: 'Puesta en marcha de la plataforma en un servidor propio',
      audience: 'Administradores de sistemas'
    }
  ]
};

// Colors (matching PDF)
const COLORS = {
  primary: '3B82F6',      // Blue
  primaryDark: '2563EB',
  primaryLight: '60A5FA',
  white: 'FFFFFF',
  black: '000000',
  gray: '6B7280',
  grayLight: 'F3F4F6',
  grayDark: '374151',
  tableBg: 'DBEAFE',
  tableHeaderBg: '3B82F6'
};

// Styles
const STYLES = {
  heading1: {
    heading: HeadingLevel.HEADING_1,
    spacing: { before: 400, after: 200 },
    color: COLORS.primaryDark,
    bold: true,
    size: 48 // 24pt
  },
  heading2: {
    heading: HeadingLevel.HEADING_2,
    spacing: { before: 300, after: 150 },
    color: COLORS.black,
    bold: true,
    size: 36 // 18pt
  },
  heading3: {
    heading: HeadingLevel.HEADING_3,
    spacing: { before: 200, after: 100 },
    color: COLORS.grayDark,
    bold: true,
    size: 28 // 14pt
  },
  normal: {
    spacing: { after: 120 },
    size: 22 // 11pt
  }
};

// Function to generate slug for bookmarks (same as PDF)
function slugify(text) {
  return text
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^\w\s-]/g, '')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-')
    .trim()
    .substring(0, 40); // Limit length for Word compatibility
}

// Render Mermaid diagram to PNG image
async function renderMermaidToImage(mermaidCode, diagramIndex) {
  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });

  const page = await browser.newPage();
  await page.setViewport({ width: 800, height: 600 });

  const html = `
<!DOCTYPE html>
<html>
<head>
  <script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
  <style>
    body { margin: 0; padding: 20px; background: white; }
    .mermaid { background: white; }
  </style>
</head>
<body>
  <div class="mermaid">
${mermaidCode}
  </div>
  <script>
    mermaid.initialize({
      startOnLoad: true,
      theme: 'default',
      themeVariables: {
        primaryColor: '#dbeafe',
        primaryTextColor: '#1e40af',
        primaryBorderColor: '#3b82f6',
        lineColor: '#64748b',
        fontFamily: 'Arial, sans-serif'
      }
    });
  </script>
</body>
</html>`;

  await page.setContent(html, { waitUntil: 'networkidle0' });

  // Wait for Mermaid to render
  await page.waitForFunction(() => {
    const svg = document.querySelector('.mermaid svg');
    return svg !== null;
  }, { timeout: 10000 }).catch(() => { });

  await new Promise(resolve => setTimeout(resolve, 500));

  // Get diagram dimensions
  const diagramElement = await page.$('.mermaid');
  const boundingBox = await diagramElement.boundingBox();

  // Screenshot the diagram
  const imagePath = path.join(__dirname, `mermaid-diagram-${diagramIndex}.png`);
  await diagramElement.screenshot({ path: imagePath });

  await browser.close();

  return imagePath;
}

// Pre-render all Mermaid diagrams from markdown
async function prerenderMermaidDiagrams(markdown) {
  const diagrams = [];
  const regex = /```mermaid\n([\s\S]*?)```/g;
  let match;
  let index = 0;

  while ((match = regex.exec(markdown)) !== null) {
    const code = match[1].trim();
    console.log(`    📊 Rendering diagram ${index + 1}...`);
    try {
      const imagePath = await renderMermaidToImage(code, index);
      diagrams.push({ code, imagePath });
    } catch (err) {
      console.log(`    ⚠️ Failed to render diagram ${index + 1}: ${err.message}`);
      diagrams.push({ code, imagePath: null });
    }
    index++;
  }

  return diagrams;
}

// Parse markdown and convert to docx elements
class MarkdownToDocx {
  constructor(mermaidDiagrams = []) {
    this.elements = [];
    this.tableBuffer = null;
    this.listBuffer = null;
    this.codeBuffer = null;
    this.mermaidDiagrams = mermaidDiagrams;
    this.mermaidIndex = 0;
  }

  parseMarkdown(markdown) {
    const lines = markdown.split('\n');
    let inCodeBlock = false;
    let codeLanguage = '';
    let codeContent = [];
    let inTable = false;
    let tableRows = [];

    for (let i = 0; i < lines.length; i++) {
      const line = lines[i];

      // Code blocks
      if (line.startsWith('```')) {
        if (!inCodeBlock) {
          inCodeBlock = true;
          codeLanguage = line.slice(3).trim();
          codeContent = [];
        } else {
          inCodeBlock = false;
          this.addCodeBlock(codeContent.join('\n'), codeLanguage);
          codeLanguage = '';
          codeContent = [];
        }
        continue;
      }

      if (inCodeBlock) {
        codeContent.push(line);
        continue;
      }

      // Tables
      if (line.includes('|') && line.trim().startsWith('|')) {
        if (!inTable) {
          inTable = true;
          tableRows = [];
        }
        // Skip separator rows
        if (!/^\|[\s\-:|]+\|$/.test(line)) {
          tableRows.push(this.parseTableRow(line));
        }
        continue;
      } else if (inTable) {
        inTable = false;
        this.addTable(tableRows);
        tableRows = [];
      }

      // Headers
      if (line.startsWith('# ')) {
        this.addHeading(line.slice(2).trim(), 1);
        continue;
      }
      if (line.startsWith('## ')) {
        this.addHeading(line.slice(3).trim(), 2);
        continue;
      }
      if (line.startsWith('### ')) {
        this.addHeading(line.slice(4).trim(), 3);
        continue;
      }
      if (line.startsWith('#### ')) {
        this.addHeading(line.slice(5).trim(), 4);
        continue;
      }

      // Horizontal rule
      if (/^---+$/.test(line.trim()) || /^\*\*\*+$/.test(line.trim())) {
        this.addHorizontalRule();
        continue;
      }

      // Lists
      if (line.match(/^[\s]*[-*]\s/)) {
        this.addListItem(line.replace(/^[\s]*[-*]\s/, '').trim());
        continue;
      }
      if (line.match(/^[\s]*\d+\.\s/)) {
        this.addListItem(line.replace(/^[\s]*\d+\.\s/, '').trim(), true);
        continue;
      }

      // Image
      const imgMatch = line.match(/!\[(.*?)\]\((.*?)\)/);
      if (imgMatch) {
        this.addImage(imgMatch[2], imgMatch[1]);
        continue;
      }

      // Regular paragraph
      if (line.trim()) {
        this.addParagraph(line.trim());
      }
    }

    // Close any open table
    if (inTable && tableRows.length > 0) {
      this.addTable(tableRows);
    }

    return this.elements;
  }

  parseInlineFormatting(text) {
    const runs = [];
    let remaining = text;

    while (remaining.length > 0) {
      // Bold + Italic
      const boldItalicMatch = remaining.match(/^\*\*\*(.*?)\*\*\*/);
      if (boldItalicMatch) {
        runs.push(new TextRun({ text: boldItalicMatch[1], bold: true, italics: true }));
        remaining = remaining.slice(boldItalicMatch[0].length);
        continue;
      }

      // Bold
      const boldMatch = remaining.match(/^\*\*(.*?)\*\*/);
      if (boldMatch) {
        runs.push(new TextRun({ text: boldMatch[1], bold: true }));
        remaining = remaining.slice(boldMatch[0].length);
        continue;
      }

      // Italic
      const italicMatch = remaining.match(/^\*(.*?)\*/);
      if (italicMatch) {
        runs.push(new TextRun({ text: italicMatch[1], italics: true }));
        remaining = remaining.slice(italicMatch[0].length);
        continue;
      }

      // Inline code
      const codeMatch = remaining.match(/^`([^`]+)`/);
      if (codeMatch) {
        runs.push(new TextRun({
          text: codeMatch[1],
          font: 'Consolas',
          size: 20,
          shading: { fill: COLORS.grayLight }
        }));
        remaining = remaining.slice(codeMatch[0].length);
        continue;
      }

      // Link
      const linkMatch = remaining.match(/^\[([^\]]+)\]\(([^)]+)\)/);
      if (linkMatch) {
        runs.push(new TextRun({ text: linkMatch[1], color: COLORS.primary, underline: {} }));
        remaining = remaining.slice(linkMatch[0].length);
        continue;
      }

      // Regular text - find next special character
      const nextSpecial = remaining.search(/[\*`\[]/);
      if (nextSpecial === -1) {
        runs.push(new TextRun({ text: remaining }));
        break;
      } else if (nextSpecial === 0) {
        // Special character without match, treat as normal text
        runs.push(new TextRun({ text: remaining[0] }));
        remaining = remaining.slice(1);
      } else {
        runs.push(new TextRun({ text: remaining.slice(0, nextSpecial) }));
        remaining = remaining.slice(nextSpecial);
      }
    }

    return runs.length > 0 ? runs : [new TextRun({ text })];
  }

  addHeading(text, level) {
    const style = level === 1 ? STYLES.heading1 :
      level === 2 ? STYLES.heading2 :
        STYLES.heading3;

    const bookmarkId = slugify(text);

    this.elements.push(
      new Paragraph({
        children: [
          new Bookmark({
            id: bookmarkId,
            children: [new TextRun({
              text: text,
              bold: style.bold,
              size: style.size,
              color: style.color
            })]
          })
        ],
        heading: style.heading,
        spacing: style.spacing
      })
    );
  }

  addParagraph(text) {
    this.elements.push(
      new Paragraph({
        children: this.parseInlineFormatting(text),
        spacing: STYLES.normal.spacing
      })
    );
  }

  addListItem(text, ordered = false) {
    this.elements.push(
      new Paragraph({
        children: [
          new TextRun({ text: ordered ? '• ' : '• ' }),
          ...this.parseInlineFormatting(text)
        ],
        spacing: { after: 60 },
        indent: { left: 360 }
      })
    );
  }

  addCodeBlock(code, language) {
    const lines = code.split('\n');

    // If it's a mermaid diagram, insert the pre-rendered image
    if (language === 'mermaid') {
      const diagram = this.mermaidDiagrams[this.mermaidIndex];
      this.mermaidIndex++;

      if (diagram && diagram.imagePath && fs.existsSync(diagram.imagePath)) {
        try {
          const imageData = fs.readFileSync(diagram.imagePath);
          this.elements.push(
            new Paragraph({
              children: [
                new ImageRun({
                  data: imageData,
                  transformation: { width: 550, height: 300 }
                })
              ],
              alignment: AlignmentType.CENTER,
              spacing: { before: 200, after: 200 }
            })
          );
          return;
        } catch (err) {
          // Fall through to placeholder
        }
      }

      // Fallback placeholder if image not available
      this.elements.push(
        new Paragraph({
          children: [new TextRun({
            text: '📊 [Diagrama - Ver versión PDF para visualización completa]',
            italics: true,
            color: COLORS.gray
          })],
          shading: { fill: COLORS.grayLight },
          spacing: { before: 120, after: 120 }
        })
      );
      return;
    }

    this.elements.push(
      new Paragraph({
        children: [new TextRun({
          text: lines.join('\n'),
          font: 'Consolas',
          size: 18
        })],
        shading: { fill: COLORS.grayLight },
        spacing: { before: 120, after: 120 }
      })
    );
  }

  parseTableRow(line) {
    return line
      .split('|')
      .filter(cell => cell.trim() !== '')
      .map(cell => cell.trim());
  }

  addTable(rows) {
    if (rows.length === 0) return;

    const tableRows = rows.map((row, index) => {
      return new TableRow({
        children: row.map(cellText => {
          return new TableCell({
            children: [new Paragraph({
              children: this.parseInlineFormatting(cellText),
              spacing: { before: 60, after: 60 }
            })],
            shading: index === 0 ? {
              fill: COLORS.tableHeaderBg,
              type: ShadingType.CLEAR
            } : undefined,
            width: { size: 100 / row.length, type: WidthType.PERCENTAGE }
          });
        }),
        tableHeader: index === 0
      });
    });

    this.elements.push(
      new Table({
        rows: tableRows,
        width: { size: 100, type: WidthType.PERCENTAGE },
        borders: {
          top: { style: BorderStyle.SINGLE, size: 1, color: 'E5E7EB' },
          bottom: { style: BorderStyle.SINGLE, size: 1, color: 'E5E7EB' },
          left: { style: BorderStyle.SINGLE, size: 1, color: 'E5E7EB' },
          right: { style: BorderStyle.SINGLE, size: 1, color: 'E5E7EB' },
          insideHorizontal: { style: BorderStyle.SINGLE, size: 1, color: 'E5E7EB' },
          insideVertical: { style: BorderStyle.SINGLE, size: 1, color: 'E5E7EB' }
        }
      })
    );

    // Add spacing after table
    this.elements.push(new Paragraph({ children: [] }));
  }

  addImage(imagePath, altText) {
    const fullPath = path.join(CONFIG.docsPath, imagePath);

    if (fs.existsSync(fullPath)) {
      try {
        const imageData = fs.readFileSync(fullPath);
        this.elements.push(
          new Paragraph({
            children: [
              new ImageRun({
                data: imageData,
                transformation: { width: 550, height: 350 }
              })
            ],
            alignment: AlignmentType.CENTER,
            spacing: { before: 200, after: 100 }
          })
        );
        // Caption
        this.elements.push(
          new Paragraph({
            children: [new TextRun({ text: altText, italics: true, size: 18, color: COLORS.gray })],
            alignment: AlignmentType.CENTER,
            spacing: { after: 200 }
          })
        );
      } catch (err) {
        this.addImagePlaceholder(altText, imagePath);
      }
    } else {
      this.addImagePlaceholder(altText, imagePath);
    }
  }

  addImagePlaceholder(altText, imagePath) {
    this.elements.push(
      new Paragraph({
        children: [new TextRun({ text: `📷 ${altText}`, color: COLORS.gray })],
        alignment: AlignmentType.CENTER,
        spacing: { before: 200, after: 200 }
      })
    );
  }

  addHorizontalRule() {
    this.elements.push(
      new Paragraph({
        children: [],
        border: { bottom: { style: BorderStyle.SINGLE, size: 6, color: COLORS.grayLight } },
        spacing: { before: 200, after: 200 }
      })
    );
  }
}

function createCoverPage(doc) {
  // Use the generated cover image (same as PDF)
  const coverPath = path.join(__dirname, `cover-${doc.cover}.png`);

  if (fs.existsSync(coverPath)) {
    const coverData = fs.readFileSync(coverPath);
    return [
      new Paragraph({
        children: [
          new ImageRun({
            data: coverData,
            transformation: { width: 595, height: 770 } // A4 proportions
          })
        ],
        alignment: AlignmentType.CENTER
      }),
      new Paragraph({
        children: [new PageBreak()]
      })
    ];
  }

  // Fallback if cover image doesn't exist
  return [
    new Paragraph({ children: [], spacing: { before: 2000 } }),
    new Paragraph({
      children: [new TextRun({ text: '📋', size: 144 })],
      alignment: AlignmentType.CENTER,
      spacing: { after: 400 }
    }),
    new Paragraph({
      children: [new TextRun({
        text: 'MiBoleta',
        bold: true,
        size: 72,
        color: COLORS.primaryDark
      })],
      alignment: AlignmentType.CENTER,
      spacing: { after: 200 }
    }),
    new Paragraph({
      children: [new TextRun({
        text: doc.title,
        size: 48,
        color: COLORS.primary
      })],
      alignment: AlignmentType.CENTER,
      spacing: { after: 400 }
    }),
    new Paragraph({
      children: [new TextRun({
        text: doc.subtitle,
        size: 24,
        color: COLORS.gray,
        italics: true
      })],
      alignment: AlignmentType.CENTER,
      spacing: { after: 800 }
    }),
    new Paragraph({
      children: [new PageBreak()]
    })
  ];
}

function createTOCPage(headings) {
  const tocItems = headings.filter(h => h.level <= 2).map(heading => {
    const indent = heading.level > 1 ? 720 : 0; // 0.5 inch indent for subheadings
    const bookmarkId = slugify(heading.text);

    return new Paragraph({
      children: [
        new InternalHyperlink({
          anchor: bookmarkId,
          children: [new TextRun({
            text: heading.text,
            size: heading.level === 1 ? 24 : 22,
            color: COLORS.primary,
            underline: {}
          })]
        })
      ],
      spacing: { after: heading.level === 1 ? 120 : 60 },
      indent: { left: indent }
    });
  });

  return [
    new Paragraph({
      children: [new TextRun({
        text: '📑 Tabla de Contenidos',
        bold: true,
        size: 48,
        color: COLORS.primaryDark
      })],
      spacing: { after: 400 }
    }),
    ...tocItems,
    new Paragraph({
      children: [new PageBreak()]
    })
  ];
}

// Extract headings from markdown
function extractHeadings(markdown) {
  const headings = [];
  const regex = /^(#{1,3})\s+(.+)$/gm;
  let match;

  while ((match = regex.exec(markdown)) !== null) {
    const level = match[1].length;
    const text = match[2].trim();
    headings.push({ level, text });
  }

  return headings;
}

async function generateSingleWord(docConfig) {
  const inputPath = path.join(CONFIG.docsPath, docConfig.input);

  if (!fs.existsSync(inputPath)) {
    console.error(`  ❌ File not found: ${inputPath}`);
    return false;
  }

  const markdown = fs.readFileSync(inputPath, 'utf-8');

  console.log(`  📋 Parsing markdown...`);

  // Extract headings for TOC
  const headings = extractHeadings(markdown);
  console.log(`  📑 Found ${headings.length} headings for TOC`);

  // Pre-render Mermaid diagrams as images
  console.log(`  📊 Rendering Mermaid diagrams...`);
  const mermaidDiagrams = await prerenderMermaidDiagrams(markdown);
  console.log(`  ✅ Rendered ${mermaidDiagrams.length} diagrams`);

  const parser = new MarkdownToDocx(mermaidDiagrams);
  const contentElements = parser.parseMarkdown(markdown);

  console.log(`  📝 Creating document with ${contentElements.length} elements...`);

  // Create document
  const doc = new Document({
    title: `MiBoleta - ${docConfig.title}`,
    description: docConfig.subtitle,
    creator: 'MiBoleta',
    styles: {
      paragraphStyles: [
        {
          id: "Normal",
          name: "Normal",
          run: { font: "Calibri", size: 22 }
        }
      ]
    },
    sections: [{
      properties: {
        page: {
          margin: {
            top: convertInchesToTwip(1),
            right: convertInchesToTwip(1),
            bottom: convertInchesToTwip(1),
            left: convertInchesToTwip(1)
          }
        }
      },
      headers: {
        default: new Header({
          children: [new Paragraph({
            children: [new TextRun({
              text: `MiBoleta - ${docConfig.title}`,
              size: 18,
              color: COLORS.gray
            })]
          })]
        })
      },
      footers: {
        default: new Footer({
          children: [new Paragraph({
            children: [
              new TextRun({ text: 'Página ', size: 18, color: COLORS.gray }),
              new TextRun({ children: [PageNumber.CURRENT], size: 18, color: COLORS.gray }),
              new TextRun({ text: ' de ', size: 18, color: COLORS.gray }),
              new TextRun({ children: [PageNumber.TOTAL_PAGES], size: 18, color: COLORS.gray })
            ],
            alignment: AlignmentType.CENTER
          })]
        })
      },
      children: [
        ...createCoverPage(docConfig),
        ...createTOCPage(headings),
        ...contentElements
      ]
    }]
  });

  // Generate document
  const buffer = await Packer.toBuffer(doc);
  fs.mkdirSync(CONFIG.salidaPath, { recursive: true });
  const outputPath = path.join(CONFIG.salidaPath, docConfig.output);
  fs.writeFileSync(outputPath, buffer);

  const size = (fs.statSync(outputPath).size / 1024 / 1024).toFixed(2);
  console.log(`  ✅ Generated: ${docConfig.output} (${size} MB)`);

  return true;
}

async function generateWords() {
  console.log('🚀 Starting Word document generation...\n');

  for (const doc of CONFIG.documents) {
    console.log(`📝 Processing: ${doc.title}`);
    await generateSingleWord(doc);
    console.log('');
  }

  console.log('═'.repeat(50));
  console.log('✅ All Word documents generated successfully!');
  console.log('═'.repeat(50));
  console.log('\nOutput files:');
  CONFIG.documents.forEach(doc => {
    console.log(`  📁 ${path.join(CONFIG.salidaPath, doc.output)}`);
  });
  console.log('\n⚠️  Note: Update TOC in Word by right-clicking the table of contents and selecting "Update Field"');
}

// Run
generateWords().catch(err => {
  console.error('❌ Error generating Word documents:', err);
  process.exit(1);
});
