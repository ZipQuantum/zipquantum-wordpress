import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(scriptDir, '..');
const potPath = path.join(rootDir, 'languages', 'zipquantum-smart-links.pot');
const cataloguePath = path.join(rootDir, 'translations', 'drafts.json');
const reviewDir = path.join(rootDir, 'translations', 'review');
const moDir = path.join(rootDir, 'output', 'translation-drafts');

const [pot, catalogueText] = await Promise.all([
  fs.readFile(potPath, 'utf8'),
  fs.readFile(cataloguePath, 'utf8'),
]);
const catalogue = JSON.parse(catalogueText);

function parsePotEntries(source) {
  return source
    .replace(/\r\n/g, '\n')
    .trim()
    .split(/\n{2,}/)
    .map((block) => {
      const lines = block.split('\n');
      const msgidLine = lines.find((line) => line.startsWith('msgid '));
      if (!msgidLine) return null;
      const msgid = JSON.parse(msgidLine.slice('msgid '.length));
      if (!msgid) return null;
      return {
        comments: lines.filter((line) => line.startsWith('#')),
        msgid,
      };
    })
    .filter(Boolean);
}

const entries = parsePotEntries(pot);
const potMessages = entries.map((entry) => entry.msgid);
if (JSON.stringify(potMessages) !== JSON.stringify(catalogue.source)) {
  throw new Error('The POT catalogue changed. Regenerate and review translations before building drafts.');
}

function poQuoted(value) {
  return JSON.stringify(value);
}

function metadata(locale, config) {
  return [
    'Project-Id-Version: ZipQuantum – Smart Links & QR Codes 1.0.0',
    'Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/zipquantum-smart-links',
    'POT-Creation-Date: 2026-09-02T10:00:00+02:00',
    "PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE",
    'Last-Translator: HUMAN REVIEW REQUIRED',
    `Language-Team: ${config.languageTeam}`,
    `Language: ${locale}`,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    `Plural-Forms: ${config.pluralForms}`,
    'X-Generator: ZipQuantum translation draft builder',
    'X-Translation-Status: DRAFT - HUMAN REVIEW REQUIRED',
    'X-Domain: zipquantum-smart-links',
  ];
}

function renderPo(locale, config) {
  const header = metadata(locale, config);
  const blocks = [
    [
      '# Copyright (C) 2026 Xaere',
      '# This file is distributed under the GPL-2.0-or-later.',
      '# DRAFT: do not submit to WordPress.org before review by a fluent human translator.',
      'msgid ""',
      'msgstr ""',
      ...header.map((line) => poQuoted(`${line}\n`)),
    ].join('\n'),
  ];

  for (let index = 0; index < entries.length; index += 1) {
    blocks.push([
      ...entries[index].comments,
      `msgid ${poQuoted(entries[index].msgid)}`,
      `msgstr ${poQuoted(config.translations[index])}`,
    ].join('\n'));
  }

  return `${blocks.join('\n\n')}\n`;
}

function compileMo(locale, config) {
  const messages = new Map([['', `${metadata(locale, config).join('\n')}\n`]]);
  entries.forEach((entry, index) => messages.set(entry.msgid, config.translations[index]));
  const pairs = [...messages.entries()]
    .map(([original, translation]) => ({
      original: Buffer.from(original, 'utf8'),
      translation: Buffer.from(translation, 'utf8'),
    }))
    .sort((a, b) => Buffer.compare(a.original, b.original));

  const count = pairs.length;
  const originalsTableOffset = 28;
  const translationsTableOffset = originalsTableOffset + count * 8;
  const originalsDataOffset = translationsTableOffset + count * 8;
  const originalsLength = pairs.reduce((sum, pair) => sum + pair.original.length + 1, 0);
  const translationsDataOffset = originalsDataOffset + originalsLength;
  const originalsData = Buffer.concat(pairs.map((pair) => Buffer.concat([pair.original, Buffer.from([0])])));
  const translationsData = Buffer.concat(pairs.map((pair) => Buffer.concat([pair.translation, Buffer.from([0])])));
  const output = Buffer.alloc(translationsDataOffset + translationsData.length);

  output.writeUInt32LE(0x950412de, 0);
  output.writeUInt32LE(0, 4);
  output.writeUInt32LE(count, 8);
  output.writeUInt32LE(originalsTableOffset, 12);
  output.writeUInt32LE(translationsTableOffset, 16);
  output.writeUInt32LE(0, 20);
  output.writeUInt32LE(0, 24);

  let originalCursor = originalsDataOffset;
  let translationCursor = translationsDataOffset;
  pairs.forEach((pair, index) => {
    output.writeUInt32LE(pair.original.length, originalsTableOffset + index * 8);
    output.writeUInt32LE(originalCursor, originalsTableOffset + index * 8 + 4);
    output.writeUInt32LE(pair.translation.length, translationsTableOffset + index * 8);
    output.writeUInt32LE(translationCursor, translationsTableOffset + index * 8 + 4);
    originalCursor += pair.original.length + 1;
    translationCursor += pair.translation.length + 1;
  });
  originalsData.copy(output, originalsDataOffset);
  translationsData.copy(output, translationsDataOffset);
  return output;
}

function verifyMo(buffer, locale, config) {
  if (buffer.readUInt32LE(0) !== 0x950412de) {
    throw new Error(`${locale} MO file has an invalid magic number.`);
  }
  const count = buffer.readUInt32LE(8);
  const originalsTableOffset = buffer.readUInt32LE(12);
  const translationsTableOffset = buffer.readUInt32LE(16);
  const decoded = new Map();
  for (let index = 0; index < count; index += 1) {
    const originalLength = buffer.readUInt32LE(originalsTableOffset + index * 8);
    const originalOffset = buffer.readUInt32LE(originalsTableOffset + index * 8 + 4);
    const translationLength = buffer.readUInt32LE(translationsTableOffset + index * 8);
    const translationOffset = buffer.readUInt32LE(translationsTableOffset + index * 8 + 4);
    decoded.set(
      buffer.subarray(originalOffset, originalOffset + originalLength).toString('utf8'),
      buffer.subarray(translationOffset, translationOffset + translationLength).toString('utf8'),
    );
  }
  entries.forEach((entry, index) => {
    if (decoded.get(entry.msgid) !== config.translations[index]) {
      throw new Error(`${locale} MO verification failed for: ${entry.msgid}`);
    }
  });
}

await Promise.all([fs.mkdir(reviewDir, { recursive: true }), fs.mkdir(moDir, { recursive: true })]);

for (const [locale, config] of Object.entries(catalogue.locales)) {
  if (config.translations.length !== entries.length || config.translations.some((value) => !value)) {
    throw new Error(`${locale} must contain ${entries.length} non-empty translations.`);
  }
  await fs.writeFile(
    path.join(reviewDir, `zipquantum-smart-links-${locale}.po`),
    renderPo(locale, config),
    'utf8',
  );
  const mo = compileMo(locale, config);
  verifyMo(mo, locale, config);
  await fs.writeFile(path.join(moDir, `zipquantum-smart-links-${locale}.mo`), mo);
}

console.log(`Generated ${Object.keys(catalogue.locales).length} PO drafts and test MO files (${entries.length} strings per locale).`);
