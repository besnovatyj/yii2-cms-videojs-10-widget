# yii2-cms-videojs-10-widget

VideoJS v10 (Web Components) виджет для Yii2 CMS. Замена старого `yii2-cms-videojs-widget` на базе VideoJS v8.

## Возможности

- **Локальное видео MP4** — через VideoJS v10 Web Components (`<video-player>`)
- **Внешние платформы** — YouTube, Rutube, VK, Vimeo через iframe
- **Click-to-play overlay** — постер с кнопкой воспроизведения (iframe загружается только после клика)
- **Шорткоды** — полная совместимость с `besnovatyj/yii2-cms-shortcode`
- **Accessibility** — aria-label, role=button, keyboard navigation (Tab + Enter/Space)
- **Bootstrap 5** — responsive через `ratio ratio-16x9`
- **TypeScript strict** — весь клиентский код на TypeScript

## Установка

### 1. Сборка TypeScript (требуется для VideoJS v10 Web Components)

```bash
cd app/packages/besnovatyj/yii2-cms-videojs-10-widget
npm install
npm run build
```

Без сборки работает **только iframe режим** (YouTube, Rutube, VK, Vimeo).
VideoJS v10 Web Components (`<video-player>`) требуют собранного `player.js`.

### 2. Регистрация в шорткодах (через admin или migration)

| Поле        | Значение                     |
|-------------|------------------------------|
| shortcode   | `videojs`                    |
| type        | `widget`                     |
| replacement | `\Besnovatyj\VideoJs\Player` |

---

## Использование

### PHP API — локальное видео (VideoJS v10)

```php
echo \Besnovatyj\VideoJs\Player::widget([
    'sources' => [
        ['src' => '/videos/movie.mp4', 'type' => 'video/mp4'],
        ['src' => '/videos/movie.webm', 'type' => 'video/webm'], // fallback
    ],
    'poster' => '/images/poster.jpg',
    'title' => 'Название фильма',
]);
```

> **По умолчанию `lazyVideo = true`** — до клика показывается постер + кнопка ▶,
> а `<video-player>` (скин v10 с панелью управления) монтируется и стартует только
> по клику. Чтобы рендерить плеер сразу (разметка ниже), задайте `'lazyVideo' => false`.

**Генерирует HTML:**

```html

<video-player>
    <video-skin>
        <video controls preload="auto" poster="/images/poster.jpg" playsinline aria-label="Название фильма">
            <source src="/videos/movie.mp4" type="video/mp4">
            <source src="/videos/movie.webm" type="video/webm">
        </video>
    </video-skin>
</video-player>
```

---

### PHP API — iframe (YouTube, Rutube, VK, Vimeo)

```php
// Click-to-play (по умолчанию, lazyIframe = true)
echo \Besnovatyj\VideoJs\Player::widget([
    'iframeSrc' => 'https://www.youtube.com/embed/VIDEO_ID',
    'iframeAllow' => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture',
    'iframeReferrerPolicy' => 'strict-origin-when-cross-origin',
    'poster' => 'https://img.youtube.com/vi/VIDEO_ID/mqdefault.jpg',
    'title' => 'Название видео',
]);

// Прямой iframe (без click-to-play)
echo \Besnovatyj\VideoJs\Player::widget([
    'iframeSrc' => 'https://rutube.ru/play/embed/VIDEO_ID',
    'iframeAllow' => 'autoplay; fullscreen',
    'lazyIframe' => false,
]);
```

---

### PHP API — данные из модуля актёров (PersonVideo)

```php
// PersonVideo содержит все необходимые данные для iframe
foreach ($person->videos as $video) {
    echo \Besnovatyj\VideoJs\Player::widget([
        'iframeSrc'             => $video->iframe_url,
        'iframeAllow'           => $video->iframe_allow,
        'iframeReferrerPolicy'  => $video->iframe_referrerpolicy,
        'poster'                => $video->thumbnail_url,
        'title'                 => $person->name . ' — видео',
    ]);
}
```

---

### Шорткоды (besnovatyj/yii2-cms-shortcode)

#### ВАЖНОЕ!

Ссылки для вставки видео со сторонних видеохостингов должны быть embed, например:

```text
https://rutube.ru/embed/610084de88e90f53f038ebee2eef8dbb/
```

Обычные видео видеохостинги не разрешают встраивать в чужие страницы

| ❌ Неверно (страница)             | ✅ Верно (embed)                    |
|----------------------------------|------------------------------------|
| `https://rutube.ru/video/ID/`    | `https://rutube.ru/play/embed/ID`  |
| `https://youtube.com/watch?v=ID` | `https://www.youtube.com/embed/ID` |
| `https://vk.com/video...`        | `https://vk.com/video_ext.php?...` |

#### Локальное видео

```
[videojs, poster="/files/poster.jpg"][source src="/files/movie.mp4" type="video/mp4"][/videojs]
```

Несколько форматов (fallback):

```
[videojs, poster="/files/poster.jpg"]
  [source src="/files/movie.mp4" type="video/mp4"]
  [source src="/files/movie.webm" type="video/webm"]
[/videojs]
```

#### Iframe (YouTube)

```
[videojs, iframeSrc="https://www.youtube.com/embed/VIDEO_ID" iframeAllow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" poster="https://img.youtube.com/vi/VIDEO_ID/mqdefault.jpg" title="Название видео"][/videojs]
```

#### Iframe (Rutube)

```
[videojs, iframeSrc="https://rutube.ru/play/embed/VIDEO_ID" iframeAllow="autoplay; fullscreen" poster="THUMBNAIL_URL"][/videojs]
```

#### Без click-to-play

```
[videojs, iframeSrc="https://www.youtube.com/embed/VIDEO_ID" lazyIframe=0][/videojs]
```

> **Примечание:** `lazyIframe=0` через шорткод — атрибуты без кавычек парсятся как целые числа.
> `0` (int) будет falsy в PHP. Если нужно false, используйте `lazyIframe=false` (будет строка — truthy!).
> Надёжнее задавать через PHP API.

---

### Параметры виджета

| Свойство               | Тип       | По умолчанию | Описание                                                  |
|------------------------|-----------|--------------|-----------------------------------------------------------|
| `poster`               | `?string` | `null`       | URL постера (превью)                                      |
| `title`                | `?string` | `null`       | Заголовок для aria-label                                  |
| `width`                | `?int`    | `null`       | Ширина                                                    |
| `height`               | `?int`    | `null`       | Высота                                                    |
| `sources`              | `array`   | `[]`         | Список источников `[['src', 'type'], ...]`                |
| `controls`             | `bool`    | `true`       | Элементы управления                                       |
| `autoplay`             | `bool`    | `false`      | Автовоспроизведение                                       |
| `preload`              | `string`  | `'auto'`     | Предзагрузка: `'auto'`, `'metadata'`, `'none'`            |
| `muted`                | `bool`    | `false`      | Без звука                                                 |
| `playsinline`          | `bool`    | `true`       | Inline на мобильных                                       |
| `lazyVideo`            | `bool`    | `true`       | Click-to-play для локального видео: постер + ▶, `<video-player>` монтируется по клику |
| `content`              | `?string` | `null`       | Внутренний контент шорткода (авто-парсинг `[source ...]`) |
| `iframeSrc`            | `?string` | `null`       | URL iframe (YouTube, Rutube, VK, Vimeo)                   |
| `iframeAllow`          | `string`  | `''`         | Атрибут `allow` для iframe                                |
| `iframeReferrerPolicy` | `string`  | `''`         | Атрибут `referrerpolicy`                                  |
| `lazyIframe`           | `bool`    | `true`       | Click-to-play (постер + кнопка)                           |
| `options`              | `array`   | `[]`         | Доп. HTML-атрибуты                                        |

---

## Структура модуля

```
yii2-cms-videojs-10-widget/
├── composer.json              # Pакет Yii2 расширение, namespace Besnovatyj\VideoJs\
├── package.json               # npm: @videojs/html, esbuild, typescript
├── tsconfig.json              # TypeScript strict config
├── esbuild.config.mjs         # Сборка TS → src/media/player.js + player.css
├── README.md                  # Этот файл (инструкция по использованию)
├── DEVELOPMENT.md             # Руководство по разработке и расширению
└── src/
    ├── Player.php             # Основной Yii2 Widget
    ├── PlayerAssets.php       # Yii2 AssetBundle → src/media/
    ├── ts/
    │   ├── index.ts           # TypeScript entry: VideoJS v10 + iframe click-to-play
    │   └── styles.css         # Custom CSS (overlay), импортируется в index.ts
    └── media/                 # Собранные assets (output npm run build)
        ├── player.js          # Бандл: @videojs/html + iframe JS
        └── player.css         # Бандл: VideoJS v10 skin + overlay CSS
```

---

## Команды разработки

```bash
# Установка зависимостей
npm install

# Однократная сборка (production, minified)
npm run build

# Сборка с watch (development)
npm run dev
```

---

## Совместимость браузеров

VideoJS v10 использует Web Components (Custom Elements v1) — требуются современные браузеры:

| Браузер | Минимальная версия |
|---------|--------------------|
| Chrome  | 100+               |
| Firefox | 100+               |
| Safari  | 15+                |
| Edge    | 100+               |

> **IE11 не поддерживается** — VideoJS v10 нативно использует ESM и Web Components.
