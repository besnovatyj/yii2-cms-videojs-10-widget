<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\VideoJs;

use yii\base\InvalidConfigException;
use yii\base\Widget;
use yii\helpers\Html;

/**
 * Виджет видеоплеера VideoJS v10 (Web Components).
 *
 * Два режима работы:
 * 1. **VideoJS режим** (локальный MP4) — через Web Components <video-player> + <video-skin>.
 *    Активируется когда задан $sources (или $content содержит [source ...] теги).
 * 2. **Iframe режим** (YouTube, Rutube, VK, Vimeo) — <iframe> с опциональным click-to-play.
 *    Активируется когда задан $iframeSrc.
 *
 * Режим определяется автоматически в методе run().
 *
 * ## Совместимость с шорткодами (besnovatyj/yii2-cms-shortcode)
 *
 * Виджет принимает свойство $content с вложенными [source ...] тегами.
 * ShortcodeContent передаёт внутренний контент шорткода в свойство $content,
 * а виджет сам парсит их своим regex (т.к. 'source' не зарегистрирован как шорткод).
 *
 * Пример шорткодов:
 * ```
 * [videojs, poster="/img/poster.jpg"][source src="/video.mp4" type="video/mp4"][/videojs]
 * [videojs, iframeSrc="https://www.youtube.com/embed/ID" iframeAllow="..." poster="URL"][/videojs]
 * ```
 *
 * Вертикальное видео (пропорция обёртки задаётся через aspectRatio или парой width/height):
 * ```
 * [videojs, aspectRatio="9:16" poster="/preview.png"][source src="/vertical.mp4" type="video/mp4"][/videojs]
 * [videojs, width=1080 height=1920 poster="/preview.png"][source src="/vertical.mp4" type="video/mp4"][/videojs]
 * ```
 *
 * ## Использование с модулем актёров (PersonVideo)
 *
 * ```php
 * echo \Besnovatyj\VideoJs\Player::widget([
 *     'iframeSrc'             => $video->iframe_url,
 *     'iframeAllow'           => $video->iframe_allow,
 *     'iframeReferrerPolicy'  => $video->iframe_referrerpolicy,
 *     'poster'                => $video->thumbnail_url,
 *     'title'                 => $person->name . ' — видео',
 * ]);
 * ```
 *
 * ## Сборка TypeScript (требуется для VideoJS v10 Web Components)
 *
 * ```bash
 * cd app/packages/besnovatyj/yii2-cms-videojs-10-widget
 * npm install && npm run build
 * ```
 */
class Player extends Widget
{
    // ─── Общие параметры ──────────────────────────────────────────────────────

    /**
     * Дополнительные HTML-атрибуты для корневого элемента виджета.
     * Также служит хранилищем для произвольных атрибутов, переданных через шорткод (через __set()).
     *
     * @var array<string, mixed>
     */
    public array $options = [];

    /**
     * URL изображения-постера (превью видео).
     * Используется в обоих режимах: как атрибут poster для <video> и как фон для iframe overlay.
     */
    public ?string $poster = null;

    /**
     * Заголовок видео для aria-label (улучшает доступность).
     */
    public ?string $title = null;

    /**
     * Ширина видео в пикселях.
     * Для iframe-режима используется напрямую; для VideoJS режима задаётся через CSS/Bootstrap.
     */
    public ?int $width = null;

    /**
     * Высота видео в пикселях.
     * Для iframe-режима используется напрямую; для VideoJS режима задаётся через CSS/Bootstrap.
     */
    public ?int $height = null;

    /**
     * Пропорция плеера (CSS aspect-ratio) для обёртки видео.
     *
     * Перекрывает дефолтную пропорцию 16/9, зашитую в CSS-класс `.vjs-iframe-embed`,
     * инлайн-стилем `aspect-ratio`. Нужно, например, для вертикальных видео (Shorts/Reels),
     * иначе постер обрезается по краям (`background-size: cover`), а плеер остаётся горизонтальным.
     *
     * Принимаемые форматы (нормализуются к синтаксису CSS `A / B`):
     * - `"9:16"` или `"9/16"` — вертикальное видео;
     * - `"16:9"` — горизонтальное;
     * - `"0.5625"` — как одно число.
     *
     * Если свойство не задано, пропорция определяется в порядке:
     * 1. атрибут `aspectRatio` на вложенном `[source ...]` (для совместимости с существующей разметкой);
     * 2. вычисляется из $width / $height, если оба заданы (например width=1080 height=1920 → 9/16);
     * 3. иначе применяется дефолт CSS (16/9).
     *
     * Пример шорткода:
     * ```
     * [videojs, aspectRatio="9:16" poster="/poster.png"][source src="/v.mp4" type="video/mp4"][/videojs]
     * ```
     */
    public ?string $aspectRatio = null;

    // ─── VideoJS режим (локальный MP4) ───────────────────────────────────────

    /**
     * Список источников видео.
     *
     * Формат: [['src' => '/path/video.mp4', 'type' => 'video/mp4'], ...]
     *
     * Поддерживаются все атрибуты тега <source>: src, type, media, sizes, srcset.
     * Дополнительный атрибут 'data-res' можно использовать для обозначения качества.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $sources = [];

    /**
     * Показывать элементы управления плеером (controls).
     */
    public bool $controls = true;

    /**
     * Автовоспроизведение (autoplay).
     * Большинство браузеров требуют $muted = true для autoplay.
     */
    public bool $autoplay = false;

    /**
     * Предзагрузка видео.
     * Допустимые значения: 'auto', 'metadata', 'none'.
     */
    public string $preload = 'auto';

    /**
     * Воспроизведение без звука (muted).
     * Необходим для autoplay в большинстве браузеров.
     */
    public bool $muted = false;

    /**
     * Атрибут playsinline — воспроизведение inline на мобильных (без перехода в fullscreen).
     */
    public bool $playsinline = true;

    /**
     * Ленивый запуск локального видео (click-to-play).
     *
     * true (по умолчанию): до клика показывается постер + центральная кнопка ▶,
     *   а тяжёлый <video-player> (скин VideoJS v10) монтируется и стартует только
     *   после клика. Повторяет UX старого плеера (никакой панели управления до
     *   взаимодействия) и экономит ресурсы страницы — скин/JS не грузятся вхолостую.
     * false: <video-player> рендерится сразу, нижняя панель управления скина видна
     *   ещё до старта воспроизведения.
     *
     * Аналог $lazyIframe, но для локальных источников ($sources).
     */
    public bool $lazyVideo = true;

    // ─── Парсинг контента шорткода ────────────────────────────────────────────

    /**
     * Внутренний контент шорткода с вложенными [source ...] тегами.
     *
     * Заполняется автоматически системой шорткодов (besnovatyj/yii2-cms-shortcode).
     * Виджет парсит из этой строки все [source attr="val" ...] и заполняет $sources.
     *
     * Пример: "[source src='/v.mp4' type='video/mp4'][source src='/v.webm' type='video/webm']"
     */
    public ?string $content = null;

    // ─── Iframe режим (YouTube, Rutube, VK, Vimeo) ───────────────────────────

    /**
     * URL для встройки iframe.
     * Соответствует PersonVideo::$iframe_url.
     *
     * Если задан — активируется iframe режим. Формат:
     * - YouTube:  https://www.youtube.com/embed/VIDEO_ID
     * - Rutube:   https://rutube.ru/play/embed/VIDEO_ID
     * - VK:       https://vk.com/video_ext.php?...
     * - Vimeo:    https://player.vimeo.com/video/VIDEO_ID
     */
    public ?string $iframeSrc = null;

    /**
     * Значение атрибута allow для <iframe>.
     * Соответствует PersonVideo::$iframe_allow.
     *
     * Пример: "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
     */
    public string $iframeAllow = '';

    /**
     * Значение атрибута referrerpolicy для <iframe>.
     * Соответствует PersonVideo::$iframe_referrerpolicy.
     *
     * Пример: "strict-origin-when-cross-origin"
     */
    public string $iframeReferrerPolicy = '';

    /**
     * Использовать click-to-play (lazy iframe).
     *
     * true (по умолчанию): показывает постер с кнопкой воспроизведения,
     *   iframe загружается только после клика — улучшает производительность страницы.
     * false: iframe встраивается сразу с loading="lazy".
     */
    public bool $lazyIframe = true;

    // ─── Магические методы (поддержка произвольных атрибутов шорткодов) ──────

    /**
     * Перехватывает установку неизвестных свойств и сохраняет их в $options.
     * Это позволяет передавать произвольные HTML-атрибуты через шорткоды.
     *
     * {@inheritdoc}
     */
    public function __set($name, $value): void
    {
        $setter = 'set' . $name;
        if (method_exists($this, $setter)) {
            $this->$setter($value);
            return;
        }
        // Произвольные атрибуты из шорткодов (например, data-*, id, class)
        $this->options[$name] = $value;
    }

    /**
     * Разрешает установку любых свойств (для захвата атрибутов шорткодов в $options).
     *
     * {@inheritdoc}
     */
    public function canSetProperty($name, $checkVars = true, $checkBehaviors = true): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();

        // Устанавливаем уникальный ID для виджета
        if (!isset($this->options['id'])) {
            $this->options['id'] = 'vjs10-' . $this->getId();
        }

        // Парсим [source ...] теги из контента шорткода (если $sources ещё не задан)
        if (!empty($this->content) && empty($this->sources)) {
            $this->parseShortcodeContent();
        }

        // Снимаем с <source> управляющие (не HTML) атрибуты шорткода и подхватываем
        // aspectRatio как запасной источник пропорции, если не задан на самом [videojs].
        $this->sanitizeSources();

        // Регистрируем CSS/JS assets
        $this->registerAssets();
    }

    /**
     * Рендерит виджет.
     *
     * Автоматически выбирает режим:
     * - $iframeSrc задан → iframe режим (YouTube, Rutube, VK, Vimeo)
     * - $sources не пуст → VideoJS v10 режим (локальный MP4)
     *
     * {@inheritdoc}
     *
     * @throws InvalidConfigException если не задан ни $iframeSrc, ни $sources
     */
    public function run(): string
    {
        if ($this->iframeSrc !== null) {
            return $this->renderIframe();
        }

        if (!empty($this->sources)) {
            return $this->renderVideoJs();
        }

        throw new InvalidConfigException(
            'Виджет ' . static::class . ': необходимо задать $iframeSrc (iframe режим) '
            . 'или $sources (VideoJS режим). '
            . 'Для шорткода используйте [source ...] теги внутри [videojs ...][/videojs].'
        );
    }

    /**
     * Рендерит VideoJS v10 Web Component для локальных видеофайлов.
     *
     * Генерирует HTML:
     * ```html
     * <video-player>
     *   <video-skin>
     *     <video controls preload="auto" poster="..." playsinline>
     *       <source src="video.mp4" type="video/mp4">
     *     </video>
     *   </video-skin>
     * </video-player>
     * ```
     */
    private function renderVideoJs(): string
    {
        // Ленивый режим: постер + кнопка ▶, реальный <video-player> монтируется по клику.
        if ($this->lazyVideo) {
            return $this->renderLazyVideo();
        }

        // Атрибуты тега <video>
        $videoAttrs = [];

        if ($this->controls) {
            $videoAttrs['controls'] = true;
        }
        if ($this->autoplay) {
            $videoAttrs['autoplay'] = true;
        }
        if ($this->muted) {
            $videoAttrs['muted'] = true;
        }
        if ($this->playsinline) {
            $videoAttrs['playsinline'] = true;
        }

        $videoAttrs['preload'] = $this->preload;

        if ($this->poster !== null) {
            $videoAttrs['poster'] = $this->poster;
        }
        if ($this->title !== null) {
            $videoAttrs['aria-label'] = $this->title;
        }
        if ($this->width !== null) {
            $videoAttrs['width'] = $this->width;
        }
        if ($this->height !== null) {
            $videoAttrs['height'] = $this->height;
        }

        // Генерируем теги <source>
        $sourcesHtml = '';
        foreach ($this->sources as $source) {
            // self-closing тег <source>
            $sourcesHtml .= "\n        " . Html::tag('source', '', $source);
        }

        // Собираем HTML: <video-player> > <video-skin> > <video> > <source>
        $videoHtml = Html::tag('video', $sourcesHtml . "\n    ", $videoAttrs);
        $skinHtml = Html::tag('video-skin', "\n    " . $videoHtml . "\n");

        // Пропорцию задаём на host-элементе <video-player> (он display:grid; width:100%).
        $playerAttrs = [];
        $aspectRatioStyle = $this->aspectRatioStyle();
        if ($aspectRatioStyle !== '') {
            $playerAttrs['style'] = ltrim($aspectRatioStyle);
        }

        return Html::tag('video-player', "\n" . $skinHtml . "\n", $playerAttrs);
    }

    /**
     * Click-to-play overlay для локального видео ($sources).
     *
     * До клика показывает постер + центральную кнопку ▶ (та же разметка/стили,
     * что и у iframe-overlay). Источники и атрибуты <video> кладём в data-*;
     * по клику TypeScript (player.js) собирает <video-player><video-skin><video>
     * и запускает воспроизведение (клик = пользовательский жест → autoplay разрешён).
     */
    private function renderLazyVideo(): string
    {
        $wrapperAttrs = [
            // Переиспользуем .vjs-iframe-embed: он даёт aspect-ratio 16/9, overflow и
            // позиционирование центральной кнопки. От iframe-overlay отличается наличием
            // data-sources (по нему TypeScript понимает, что монтировать <video-player>).
            'class' => 'vjs-iframe-embed',
            'data-sources' => json_encode(
                array_values($this->sources),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),
            'data-controls' => $this->controls ? '1' : '0',
            'data-muted' => $this->muted ? '1' : '0',
            'data-playsinline' => $this->playsinline ? '1' : '0',
            'data-preload' => $this->preload,
            'role' => 'button',
            'aria-label' => $this->title ?? 'Воспроизвести видео',
        ];

        if ($this->title !== null) {
            $wrapperAttrs['data-title'] = $this->title;
        }

        $style = 'cursor: pointer;';
        if ($this->poster !== null) {
            $wrapperAttrs['data-poster'] = $this->poster;
            $style = 'background-image: url(' . Html::encode($this->poster) . '); cursor: pointer;';
        }
        $style .= $this->aspectRatioStyle();
        $wrapperAttrs['style'] = $style;

        // Кнопка воспроизведения (CSS-стилизована в player.css)
        $playBtnHtml = Html::tag('div', '&#9654;', [
            'class' => 'vjs-iframe-play-btn',
            'aria-hidden' => 'true',
        ]);

        return Html::tag('div', "\n" . $playBtnHtml . "\n", $wrapperAttrs);
    }

    /**
     * Рендерит iframe встройку для YouTube, Rutube, VK, Vimeo.
     *
     * С $lazyIframe = true (по умолчанию) генерирует click-to-play overlay:
     * ```html
     * <div class="vjs-iframe-embed" data-src="..." style="background-image: url(...)">
     *   <div class="vjs-iframe-play-btn" aria-hidden="true">▶</div>
     * </div>
     * ```
     *
     * С $lazyIframe = false генерирует прямой iframe:
     * ```html
     * <div class="ratio ratio-16x9">
     *   <iframe src="..." allow="..." referrerpolicy="..." allowfullscreen loading="lazy"></iframe>
     * </div>
     * ```
     */
    private function renderIframe(): string
    {
        if ($this->lazyIframe) {
            return $this->renderLazyIframe();
        }

        return $this->renderDirectIframe();
    }

    /**
     * Click-to-play overlay: постер + кнопка воспроизведения.
     * Iframe загружается только после клика (TypeScript в player.js).
     */
    private function renderLazyIframe(): string
    {
        $wrapperAttrs = [
            // Не используем Bootstrap .ratio — оно применяет .ratio > * { width:100%; height:100% }
            // ко всем прямым детям (включая .vjs-iframe-play-btn), что ломает позиционирование кнопки.
            // Высота задаётся через aspect-ratio: 16/9 в player.css (.vjs-iframe-embed).
            'class' => 'vjs-iframe-embed',
            'data-src' => $this->iframeSrc,
            'role' => 'button',
            'aria-label' => $this->title ?? 'Воспроизвести видео',
        ];

        if (!empty($this->iframeAllow)) {
            $wrapperAttrs['data-allow'] = $this->iframeAllow;
        }
        if (!empty($this->iframeReferrerPolicy)) {
            $wrapperAttrs['data-referrerpolicy'] = $this->iframeReferrerPolicy;
        }

        // Постер как фон
        $style = 'cursor: pointer;';
        if ($this->poster !== null) {
            $wrapperAttrs['data-poster'] = $this->poster;
            $style = 'background-image: url(' . Html::encode($this->poster) . '); cursor: pointer;';
        }
        $style .= $this->aspectRatioStyle();
        $wrapperAttrs['style'] = $style;

        // Кнопка воспроизведения (CSS-стилизована в player.css)
        $playBtnHtml = Html::tag('div', '&#9654;', [
            'class' => 'vjs-iframe-play-btn',
            'aria-hidden' => 'true',
        ]);

        return Html::tag('div', "\n" . $playBtnHtml . "\n", $wrapperAttrs);
    }

    /**
     * Прямой iframe без lazy loading.
     * Используется при $lazyIframe = false.
     */
    private function renderDirectIframe(): string
    {
        $iframeAttrs = [
            'src' => $this->iframeSrc,
            'allowfullscreen' => true,
            'loading' => 'lazy',
            'class' => 'w-100 h-100 border-0',
        ];

        if (!empty($this->iframeAllow)) {
            $iframeAttrs['allow'] = $this->iframeAllow;
        }
        if (!empty($this->iframeReferrerPolicy)) {
            $iframeAttrs['referrerpolicy'] = $this->iframeReferrerPolicy;
        }
        if ($this->title !== null) {
            $iframeAttrs['title'] = $this->title;
        }
        if ($this->width !== null) {
            $iframeAttrs['width'] = $this->width;
        }
        if ($this->height !== null) {
            $iframeAttrs['height'] = $this->height;
        }

        $iframeHtml = Html::tag('iframe', '', $iframeAttrs);

        // Bootstrap .ratio задаёт высоту через padding у ::before и игнорирует CSS aspect-ratio,
        // поэтому при кастомной пропорции используем собственную обёртку с aspect-ratio.
        $aspectRatio = $this->resolveAspectRatio();
        $wrapperAttrs = $aspectRatio !== null
            ? ['style' => 'position: relative; width: 100%; aspect-ratio: ' . $aspectRatio . ';']
            : ['class' => 'ratio ratio-16x9'];

        return Html::tag('div', "\n" . $iframeHtml . "\n", $wrapperAttrs);
    }

    /**
     * Парсинг [source ...] тегов из контента шорткода.
     * Заполняет $this->sources если он пуст.
     *
     * Поддерживаемые форматы атрибутов:
     * - attr="value"
     * - attr='value'
     * - attr=value
     */
    private function parseShortcodeContent(): void
    {
        if (empty($this->content)) {
            return;
        }

        $sources = [];
        preg_match_all('/\[source\s+([^]]+)]/', $this->content, $matches);

        foreach ($matches[1] as $attrString) {
            $attrs = $this->parseSourceAttributes($attrString);
            if (!empty($attrs)) {
                $sources[] = $attrs;
            } else {
                \Yii::warning(
                    'VideoJS Widget: некорректный [source] шорткод: ' . $attrString,
                    static::class
                );
            }
        }

        if (!empty($sources)) {
            $this->sources = $sources;
        }
    }

    /**
     * Парсинг строки атрибутов шорткода в ассоциативный массив.
     *
     * Поддерживает форматы: attr="val", attr='val', attr=val
     * Числовые значения приводятся к int/float. Строки экранируются через Html::encode().
     *
     * @param string $attrString Строка с атрибутами (содержимое шорткода без скобок)
     * @return array<string, string|int|float>
     */
    private function parseSourceAttributes(string $attrString): array
    {
        if (empty($attrString)) {
            return [];
        }

        $attributes = [];

        preg_match_all(
            '/(\w+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^"\'][^\s,]*))/',
            $attrString,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $key = $match[1];
            // Выбираем непустое значение из групп (двойные кавычки, одинарные кавычки, без кавычек)
            $value = $match[2] !== '' ? $match[2]
                : ($match[3] !== '' ? $match[3]
                : ($match[4] !== '' ? $match[4] : ''));

            if (is_numeric($value)) {
                $value = str_contains($value, '.') ? (float) $value : (int) $value;
            } else {
                $value = Html::encode($value);
            }

            $attributes[$key] = $value;
        }

        return $attributes;
    }

    /**
     * Убирает из источников управляющие атрибуты шорткода, не являющиеся валидными
     * атрибутами тега <source> (aspectRatio, fluid), чтобы они не попадали в разметку.
     *
     * Если пропорция не задана на самом [videojs], но присутствует на [source]
     * (aspectRatio="9:16"), подхватываем её как запасное значение — так продолжает
     * работать существующая разметка, где атрибут стоит на вложенном источнике.
     */
    private function sanitizeSources(): void
    {
        foreach ($this->sources as &$source) {
            if ($this->aspectRatio === null && isset($source['aspectRatio']) && $source['aspectRatio'] !== '') {
                $this->aspectRatio = (string) $source['aspectRatio'];
            }
            unset($source['aspectRatio'], $source['fluid']);
        }
        unset($source);
    }

    /**
     * Вычисляет значение CSS-свойства aspect-ratio для обёртки плеера.
     *
     * Приоритет: явно заданный $aspectRatio → вычисление из $width / $height → null (дефолт CSS).
     * Разделители ":" и "/" нормализуются к синтаксису CSS "A / B".
     *
     * @return string|null Например "9 / 16", "1080 / 1920" или null.
     */
    private function resolveAspectRatio(): ?string
    {
        $raw = $this->aspectRatio;

        if ($raw === null || trim($raw) === '') {
            if ($this->width !== null && $this->height !== null && $this->width > 0 && $this->height > 0) {
                return $this->width . ' / ' . $this->height;
            }
            return null;
        }

        // "9:16", "9/16", "9 / 16" → "9 / 16"; одиночное число ("0.5625") остаётся как есть.
        return preg_replace('/\s*[:\/]\s*/', ' / ', trim($raw));
    }

    /**
     * Возвращает готовый фрагмент инлайн-стиля с пропорцией (с ведущим пробелом
     * для дозаписи к существующему style) либо пустую строку.
     */
    private function aspectRatioStyle(): string
    {
        $aspectRatio = $this->resolveAspectRatio();

        return $aspectRatio !== null ? ' aspect-ratio: ' . $aspectRatio . ';' : '';
    }

    /**
     * Регистрирует CSS и JS через Yii2 AssetBundle.
     */
    private function registerAssets(): void
    {
        PlayerAssets::register($this->getView());
    }
}
