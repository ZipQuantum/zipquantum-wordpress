import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(scriptDir, '..');
const cataloguePath = path.join(rootDir, 'translations', 'readme-drafts.json');
const reviewDir = path.join(rootDir, 'translations', 'review', 'readme');
const catalogue = JSON.parse(await fs.readFile(cataloguePath, 'utf8'));

function poQuoted(value) {
  return JSON.stringify(value);
}

function assertMarkupIsPreserved(source, translation, locale) {
  for (const token of ['<strong>', '</strong>', '<code>', '</code>']) {
    const sourceCount = source.split(token).length - 1;
    const translationCount = translation.split(token).length - 1;
    if (sourceCount !== translationCount) {
      throw new Error(`${locale} must preserve ${token} in: ${source}`);
    }
  }

  for (const literal of [
    'https://zq.tn/docs/cms-integrations/',
    'https://zq.tn/privacy-policy/',
    'https://zq.tn/terms-of-service/',
    'https://zq.tn/',
    'home_url()',
    'a.zq.tn',
  ]) {
    if (source.includes(literal) && !translation.includes(literal)) {
      throw new Error(`${locale} must preserve ${literal} in: ${source}`);
    }
  }
}

function metadata(locale, config) {
  return [
    'Project-Id-Version: ZipQuantum – Smart Links & QR Codes 1.0.0 Stable Readme',
    'Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/zipquantum-smart-links',
    'PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE',
    'Last-Translator: HUMAN REVIEW REQUIRED',
    `Language-Team: ${config.languageTeam}`,
    `Language: ${config.projectLocale}`,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    `Plural-Forms: ${config.pluralForms}`,
    'X-Generator: ZipQuantum Stable Readme translation draft builder',
    'X-Translation-Status: DRAFT - HUMAN REVIEW REQUIRED',
  ];
}

function renderPo(locale, config) {
  const blocks = [
    [
      '# Translation of ZipQuantum Stable Readme.',
      '# DRAFT: do not submit to WordPress.org before review by a fluent human translator.',
      'msgid ""',
      'msgstr ""',
      ...metadata(locale, config).map((line) => poQuoted(`${line}\n`)),
    ].join('\n'),
  ];

  catalogue.source.forEach((source, index) => {
    const translation = config.translations[index];
    assertMarkupIsPreserved(source, translation, locale);
    blocks.push([
      '#. Stable Readme string.',
      `msgid ${poQuoted(source)}`,
      `msgstr ${poQuoted(translation)}`,
    ].join('\n'));
  });

  return `${blocks.join('\n\n')}\n`;
}

if (catalogue.source.length !== 58 || new Set(catalogue.source).size !== catalogue.source.length) {
  throw new Error('Stable Readme source must contain the 58 unique strings exported by GlotPress.');
}

await fs.mkdir(reviewDir, { recursive: true });
for (const [locale, config] of Object.entries(catalogue.locales)) {
  if (config.translations.length !== catalogue.source.length || config.translations.some((value) => !value)) {
    throw new Error(`${locale} must contain ${catalogue.source.length} non-empty translations.`);
  }
  await fs.writeFile(
    path.join(reviewDir, `zipquantum-smart-links-stable-readme-${config.projectLocale}.po`),
    renderPo(locale, config),
    'utf8',
  );
}

console.log(`Generated ${Object.keys(catalogue.locales).length} Stable Readme PO drafts (${catalogue.source.length} strings per locale).`);
