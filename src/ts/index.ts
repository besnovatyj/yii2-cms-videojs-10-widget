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

// ─── Click-to-play (iframe + локальное видео) ──────────────────────────────

interface IframeEmbedDataset {
    readonly src?: string;
    readonly allow?: string;
    readonly referrerpolicy?: string;
}

/** Один источник локального видео (тег <source>). */
interface VideoSource {
    readonly src: string;
    readonly type?: string;
    readonly [attr: string]: string | number | undefined;
}

/**
 * Инициализирует все .vjs-iframe-embed элементы на странице.
 *
 * Один overlay-каркас обслуживает два режима:
 *  - data-sources присутствует → локальное видео (монтируем <video-player>);
 *  - иначе → внешний iframe (YouTube/Rutube/VK/Vimeo).
 */
function initLazyEmbeds(): void {
    const embeds = document.querySelectorAll<HTMLElement>('.vjs-iframe-embed');

    embeds.forEach((embed: HTMLElement) => {
        // AbortController снимает ОБА слушателя после первой активации. Без этого
        // клики по элементам управления смонтированного <video-player> всплывали бы
        // обратно на .vjs-iframe-embed и пересоздавали плеер (перезапуск с начала).
        const controller = new AbortController();
        const { signal } = controller;

        const activate = (): void => {
            controller.abort();

            if (embed.dataset.sources) {
                handleVideoClick(embed);
            } else {
                handleIframeClick(embed);
            }
        };

        embed.addEventListener('click', activate, { signal });

        // Клавиатурная доступность (WAI-ARIA)
        embed.addEventListener('keydown', (e: KeyboardEvent) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                activate();
            }
        }, { signal });

        embed.setAttribute('tabindex', '0');
    });
}

/**
 * Заменяет click-to-play overlay на VideoJS v10 <video-player> с autoplay.
 * Клик — пользовательский жест, поэтому воспроизведение со звуком разрешено.
 */
function handleVideoClick(embed: HTMLElement): void {
    const raw = embed.dataset.sources;
    if (!raw) {
        console.warn('vjs-embed: отсутствует data-sources атрибут', embed);
        return;
    }

    let sources: VideoSource[];
    try {
        sources = JSON.parse(raw) as VideoSource[];
    } catch (err) {
        console.warn('vjs-embed: некорректный JSON в data-sources', embed, err);
        return;
    }
    if (!Array.isArray(sources) || sources.length === 0) {
        console.warn('vjs-embed: пустой список источников', embed);
        return;
    }

    // <video-player> > <video-skin> > <video> > <source> (как в PHP-рендере не-lazy режима)
    const player: HTMLElement = document.createElement('video-player');
    // Абсолютно позиционируем внутри .vjs-iframe-embed (position:relative, aspect-ratio 16/9).
    player.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;';

    const skin: HTMLElement = document.createElement('video-skin');
    const video: HTMLVideoElement = document.createElement('video');

    video.autoplay = true;
    video.controls = embed.dataset.controls !== '0';
    video.muted = embed.dataset.muted === '1';
    if (embed.dataset.playsinline !== '0') {
        video.setAttribute('playsinline', '');
    }
    video.preload = embed.dataset.preload ?? 'auto';
    if (embed.dataset.poster) {
        video.poster = embed.dataset.poster;
    }
    if (embed.dataset.title) {
        video.setAttribute('aria-label', embed.dataset.title);
    }

    for (const source of sources) {
        const sourceEl: HTMLSourceElement = document.createElement('source');
        for (const [key, value] of Object.entries(source)) {
            if (value === undefined || value === null) {
                continue;
            }
            sourceEl.setAttribute(key, String(value));
        }
        video.appendChild(sourceEl);
    }

    skin.appendChild(video);
    player.appendChild(skin);

    // Снимаем overlay-оформление, сохраняя класс .vjs-iframe-embed (в нём aspect-ratio 16/9).
    embed.style.backgroundImage = '';
    embed.style.cursor = '';
    embed.removeAttribute('role');
    embed.removeAttribute('tabindex');
    embed.innerHTML = '';
    embed.appendChild(player);

    // Страховка на случай, если autoplay-атрибут не сработает после апгрейда web-компонента.
    void video.play().catch(() => { /* автозапуск отклонён браузером — не критично */ });
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
    document.addEventListener('DOMContentLoaded', initLazyEmbeds);
} else {
    // DOM уже готов (например, скрипт подключён в конце body)
    initLazyEmbeds();
}
