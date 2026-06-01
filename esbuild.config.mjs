/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

/**
 * ESBuild config для VideoJS v10 виджета.
 *
 * Собирает src/ts/index.ts → src/media/player.js + src/media/player.css
 *
 * Использование:
 *   npm install
 *   npm run build       # однократная сборка
 *   npm run dev         # сборка с --watch
 */

import * as esbuild from 'esbuild';

const isWatch = process.argv.includes('--watch');

/** @type {import('esbuild').BuildOptions} */
const options = {
    entryPoints: ['src/ts/index.ts'],
    bundle: true,
    minify: !isWatch,
    outdir: 'src/media',
    // entryNames задаёт имя output файлов (без расширения)
    entryNames: 'player',
    format: 'iife',
    // Целевые браузеры — современные (videojs v10 не поддерживает IE)
    target: ['chrome100', 'firefox100', 'safari15', 'edge100'],
    // ESBuild автоматически извлекает CSS импорты в отдельный файл
    loader: {
        '.css': 'css',
    },
    // Игнорируем аннотации "sideEffects" из package.json зависимостей.
    // Это необходимо потому что @videojs/html помечает свой main index.js как НЕ side-effect,
    // но safe-define.js и другие define-файлы ДОЛЖНЫ быть включены в бандл.
    // Страховка на случай если ESBuild всё равно решит пропустить какой-то import.
    ignoreAnnotations: true,
    logLevel: 'info',
};

if (isWatch) {
    const ctx = await esbuild.context(options);
    await ctx.watch();
    console.log('Watching for changes...');
} else {
    await esbuild.build(options);
    console.log('Build complete: src/media/player.js + src/media/player.css');
}
