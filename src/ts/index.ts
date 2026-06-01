/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

/**
 * VideoJS v10 Widget — TypeScript entry point.
 *
 * Инициализирует:
 * 1. VideoJS v10 Web Components (@videojs/html) — регистрирует <video-player>, <video-skin>
 * 2. Iframe click-to-play — для YouTube, Rutube, VK, Vimeo
 */

// Регистрируем VideoJS v10 Web Components.
//
// Почему не `import '@videojs/html'`:
//   main (dist/default/index.js) НЕ входит в sideEffects → ESBuild пропускает.
//
// Почему не `@videojs/html/safe-define`:
//   safe-define.js — просто утилита `function safeDefine()`, не регистрирует элементы.
//
// Правильный путь через exports map ("./video/*" → "./dist/default/define/video/*.js"):
//   video/player.js — вызывает customElements.define('video-player', ...)
//   video/skin.js   — вызывает customElements.define('video-skin', ...)
//   Оба файла входят в sideEffects ("./dist/*/define/**/*.js") → ESBuild включает их.
import '@videojs/html/video/player'; // регистрирует <video-player>
import '@videojs/html/video/skin';   // регистрирует <video-skin>

// Стили VideoJS v10 (скин плеера)
import '@videojs/html/video/skin.css';

// Наши кастомные стили (overlay для iframe)
import './styles.css';

// ─── Iframe click-to-play ──────────────────────────────────────────────────

interface IframeEmbedDataset {
    readonly src?: string;
    readonly allow?: string;
    readonly referrerpolicy?: string;
}

/**
 * Инициализирует все .vjs-iframe-embed элементы на странице.
 */
function initIframeEmbeds(): void {
    const embeds = document.querySelectorAll<HTMLElement>('.vjs-iframe-embed');

    embeds.forEach((embed: HTMLElement) => {
        embed.addEventListener('click', () => handleIframeClick(embed));

        // Клавиатурная доступность (WAI-ARIA)
        embed.addEventListener('keydown', (e: KeyboardEvent) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                handleIframeClick(embed);
            }
        });

        embed.setAttribute('tabindex', '0');
    });
}

/**
 * Заменяет click-to-play overlay на реальный iframe с autoplay.
 */
function handleIframeClick(embed: HTMLElement): void {
    const dataset = embed.dataset as unknown as IframeEmbedDataset;
    const src = dataset.src;

    if (!src) {
        console.warn('vjs-iframe-embed: отсутствует data-src атрибут', embed);
        return;
    }

    // Добавляем autoplay=1 к URL (YouTube, Rutube, Vimeo поддерживают этот параметр)
    const autoplaySrc: string = src + (src.includes('?') ? '&' : '?') + 'autoplay=1';

    const iframe: HTMLIFrameElement = document.createElement('iframe');
    iframe.src = autoplaySrc;
    iframe.allow = dataset.allow ?? '';
    iframe.referrerPolicy = dataset.referrerpolicy ?? '';
    iframe.allowFullscreen = true;
    // Iframe абсолютно позиционирован внутри position:relative контейнера (.vjs-iframe-embed).
    // НЕ используем w-100/h-100 Bootstrap — они дают размер от родителя без учёта position.
    iframe.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;border:none;';
    iframe.setAttribute('loading', 'eager');
    iframe.setAttribute('title', embed.getAttribute('aria-label') ?? 'Видео');

    // Убираем overlay-стили, НО сохраняем класс vjs-iframe-embed (в нём aspect-ratio: 16/9).
    // Без этого класса контейнер потеряет высоту после удаления фонового изображения.
    embed.style.backgroundImage = '';
    embed.style.cursor = '';
    embed.removeAttribute('role');
    embed.removeAttribute('tabindex');
    embed.innerHTML = '';
    embed.appendChild(iframe);
}

// ─── Инициализация ────────────────────────────────────────────────────────

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initIframeEmbeds);
} else {
    // DOM уже готов (например, скрипт подключён в конце body)
    initIframeEmbeds();
}
