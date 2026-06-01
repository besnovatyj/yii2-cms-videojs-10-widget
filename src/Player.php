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

        return Html::tag('video-player', "\n" . $skinHtml . "\n");
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

        return Html::tag('div', "\n" . $iframeHtml . "\n", ['class' => 'ratio ratio-16x9']);
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
     * Регистрирует CSS и JS через Yii2 AssetBundle.
     */
    private function registerAssets(): void
    {
        PlayerAssets::register($this->getView());
    }
}
