#!/usr/bin/env node

import { execFileSync, spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';

const gitExecutable = process.env.ZQ_GIT
    || (process.platform === 'win32' && existsSync('C:\\Program Files\\Git\\cmd\\git.exe')
        ? 'C:\\Program Files\\Git\\cmd\\git.exe'
        : 'git');
const forbiddenPaths = [/(^|\/)\.env(?:\.|$)/i, /(^|\/)(?:id_rsa|id_ed25519|credentials)(?:\.|$)/i, /\.(?:pem|p12|pfx|key|jks|keystore|sql|sqlite|sqlite3)$/i];
const patterns = [
    '-----BEGIN (RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----',
    '(AKIA|ASIA)[A-Z0-9]{16}',
    '(ghp|gho|ghu|ghs|ghr)_[A-Za-z0-9]{30,}',
    '(sk|rk)_live_[A-Za-z0-9]{16,}',
    'xox[baprs]-[A-Za-z0-9-]{20,}',
    'eyJ[A-Za-z0-9_-]{10,}\\.[A-Za-z0-9_-]{10,}\\.[A-Za-z0-9_-]{10,}',
    '(api[_-]?key|api[_-]?secret|client[_-]?secret|password|passwd|secret|token)[[:space:]]*[:=][[:space:]]*[A-Za-z0-9+/_=-]{24,}',
];
const git = (args) => execFileSync(gitExecutable, args, { encoding: 'utf8', maxBuffer: 128 * 1024 * 1024 });
const versionable = git(['ls-files', '-z', '--cached', '--others', '--exclude-standard']).split('\0').filter(Boolean);
const findings = versionable.filter((path) => !/(^|\/)\.env\.example$/i.test(path) && forbiddenPaths.some((pattern) => pattern.test(path)));
const result = spawnSync(gitExecutable, ['grep', '--untracked', '--exclude-standard', '-I', '-l', '-E', '-e', patterns.map((pattern) => `(${pattern})`).join('|'), '--', '.', ':(exclude)scripts/secret-scan.mjs'], {
    encoding: 'utf8',
    maxBuffer: 128 * 1024 * 1024,
});
if (result.status !== 0 && result.status !== 1) {
    console.error('Secret scan could not inspect the Git worktree.');
    process.exit(2);
}
findings.push(...result.stdout.split(/\r?\n/).filter(Boolean));
if (findings.length) {
    console.error('Secret scan failed closed. Matched values are redacted; only paths follow.');
    for (const path of [...new Set(findings)].sort()) console.error(path);
    process.exit(1);
}
console.log(`Secret scan passed (${versionable.length} tracked/untracked versionable files; values never printed).`);
