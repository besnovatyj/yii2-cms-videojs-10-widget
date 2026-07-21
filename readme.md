# yii2-cms-videojs-10-widget

VideoJS v10 (Web Components) виджет для Yii2 CMS. Замена старого `yii2-cms-videojs-widget` на базе VideoJS v8.

## Возможности

- **Локальное видео MP4** — через VideoJS v10 Web Components (`<video-player>`)
- **Внешние платформы** — YouTube, Rutube, VK, Vimeo через iframe
- **Click-to-play overlay** — постер с кнопкой воспроизведения (iframe загружается только после клика)
- **Шорткоды** — полная совместимость с `besnovatyj/yii2-cms-shortcode`
- **Accessibility** — aria-label, role=button, keyboard navigation (Tab + Enter/Space)
- **Произвольное соотношение сторон** — вертикальные видео (Shorts/Reels) через `aspectRatio` или пару `width`/`height` (см. раздел [«Соотношение сторон»](#соотношение-сторон-aspect-ratio))
- **Responsive** — overlay-режимы тянутся по ширине контейнера через CSS `aspect-ratio` (`.vjs-iframe-embed`), прямой iframe — через Bootstrap `ratio ratio-16x9`
- **TypeScript strict** — весь клиентский код на TypeScript

## Установка

### 1. Сборка TypeScript (требуется для VideoJS v10 Web Components)

Из корня пакета (`vendor/besnovatyj/yii2-cms-videojs-10-widget` при source-установке
или каталог разработки в `app/packages/...`):

```bash
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
    'aspectRatio' => '16:9', // необязательно; для вертикального видео '9:16'
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
[videojs, poster="/files/poster.jpg" width=1920 height=1080][source src="/files/movie.mp4" type="video/mp4" data-res="360"][/videojs]   
```

> `width`/`height` не только задают размеры, но и определяют пропорцию плеера:
> при заданных обоих значениях (и отсутствии явного `aspectRatio`) обёртка получает
> `aspect-ratio: width / height`. Здесь `1920×1080` → горизонтальный `16/9`,
> а `1080×1920` — вертикальный `9/16`. Подробнее — раздел
> [«Соотношение сторон»](#соотношение-сторон-aspect-ratio).

Несколько форматов (fallback):

```
[videojs, poster="/files/poster.jpg" width=1920 height=1080]
  [source src="/files/movie.mp4" type="video/mp4" data-res="360"]
  [source src="/files/movie.webm" type="video/webm" data-res="720"]
[/videojs]
```

Вертикальное видео (пропорция задаётся явно или из `width`/`height` — см. раздел
[«Соотношение сторон»](#соотношение-сторон-aspect-ratio)):

```
[videojs, aspectRatio="9:16" poster="/files/vertical_preview.png"][source src="/files/vertical.mp4" type="video/mp4"][/videojs]
```

#### Локальное видео — максимум параметров

Открывающий тег можно форматировать многострочно (парсер допускает переносы строк
внутри `[videojs ...]`). Булевы параметры задавайте числом: `1` = true, `0` = false
(строка `false` в шорткоде станет truthy — не используйте её).

```
[videojs,
    poster="%staticHost%/preview.png"
    title="Заголовок видео"
    aspectRatio="9:16"
    width=1080
    height=1920
    controls=1
    autoplay=0
    muted=1
    playsinline=1
    preload="metadata"
    lazyVideo=1
]
    [source src="%staticHost%/video.mp4" type="video/mp4" data-res="1080"]
    [source src="%staticHost%/video.webm" type="video/webm" data-res="720"]
[/videojs]
```

Параметры тега `[videojs]`: `poster`, `title`, `aspectRatio`, `width`, `height`, `controls`,
`autoplay`, `muted`, `playsinline`, `preload` (`auto`/`metadata`/`none`), `lazyVideo`.
Внутри — один или несколько `[source]` (`src`, `type`, `data-res`; поддерживаются также
`media`, `sizes`, `srcset`).

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

#### Iframe — максимум параметров

```
[videojs,
    iframeSrc="https://www.youtube.com/embed/VIDEO_ID"
    iframeAllow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
    iframeReferrerPolicy="strict-origin-when-cross-origin"
    poster="%staticHost%/preview.png"
    title="Заголовок видео"
    aspectRatio="16:9"
    width=1920
    height=1080
    lazyIframe=1
]
[/videojs]
```

Параметры iframe-режима: `iframeSrc`, `iframeAllow`, `iframeReferrerPolicy`, `poster`, `title`,
`aspectRatio`, `width`, `height`, `lazyIframe`.

> Режимы взаимоисключающие: при заданном `iframeSrc` активен iframe-режим, а `sources`,
> `controls`, `autoplay`, `muted`, `preload`, `lazyVideo` игнорируются (и наоборот).

---

### Соотношение сторон (aspect ratio)

По умолчанию обёртка плеера имеет пропорцию **16/9**, зашитую в CSS-класс `.vjs-iframe-embed`
(`player.css`). Для вертикальных видео (Shorts/Reels) это приводит к тому, что постер
обрезается по краям (`background-size: cover`), а плеер остаётся горизонтальным. Чтобы
перекрыть дефолт, виджет проставляет обёртке инлайн-стиль `aspect-ratio`.

**Как определяется пропорция** (метод `resolveAspectRatio()`), в порядке приоритета:

1. **Явный `aspectRatio`** на `[videojs]` — принимает `"9:16"`, `"9/16"` или одиночное число
   (`"0.5625"`); разделители `:` и `/` нормализуются к CSS-синтаксису `A / B`.
2. **`aspectRatio` на вложенном `[source ...]`** — подхватывается как запасное значение
   (совместимость с разметкой, где атрибут ставили на источник).
3. **Вычисление из `width` / `height`** — если оба заданы и положительны
   (например `width=1080 height=1920` → `9/16`).
4. Иначе — дефолт CSS (**16/9**).

**Куда применяется.** Пропорция проставляется инлайн-стилем во всех режимах рендера:

- lazy-видео и lazy-iframe (click-to-play) — на обёртку `.vjs-iframe-embed`;
- non-lazy видео — на host-элемент `<video-player>`;
- прямой iframe — вместо Bootstrap `.ratio` (он задаёт высоту через `padding` у `::before`
  и игнорирует CSS `aspect-ratio`) используется собственная обёртка с `aspect-ratio`.

**Поведение при монтировании.** В lazy-режиме после клика TypeScript (`index.ts`) очищает
у обёртки только `background-image` и `cursor`, поэтому инлайн-стиль `aspect-ratio` сохраняется —
смонтированный `<video-player>` занимает уже вертикальный бокс (внутри `object-fit: contain`,
видео показывается целиком без обрезки).

**Сборка ассетов не требуется.** Логика полностью в PHP-рендере; `player.css`/`player.js`
(сборка esbuild из `ts/`) не меняются — `npm run build` для этой возможности не нужен.

**Совместимость.** Управляющие атрибуты `aspectRatio` и `fluid`, стоящие на `[source ...]`,
снимаются с тега `<source>` при рендере (метод `sanitizeSources()`), т.к. не являются
валидными HTML-атрибутами источника.

```
# Явная пропорция на [videojs]
[videojs, aspectRatio="9:16" poster="/preview.png"][source src="/vertical.mp4" type="video/mp4"][/videojs]

# Пропорция из размеров
[videojs, width=1080 height=1920 poster="/preview.png"][source src="/vertical.mp4" type="video/mp4"][/videojs]

# Пропорция на [source] (совместимость)
[videojs, poster="/preview.png"][source src="/vertical.mp4" type="video/mp4" aspectRatio="9:16"][/videojs]
```

---

### Параметры виджета

| Свойство               | Тип       | По умолчанию | Описание                                                  |
|------------------------|-----------|--------------|-----------------------------------------------------------|
| `poster`               | `?string` | `null`       | URL постера (превью)                                      |
| `title`                | `?string` | `null`       | Заголовок для aria-label                                  |
| `width`                | `?int`    | `null`       | Ширина; при заданной `height` задаёт пропорцию `width/height` |
| `height`               | `?int`    | `null`       | Высота; при заданной `width` задаёт пропорцию `width/height`  |
| `aspectRatio`          | `?string` | `null`       | Пропорция обёртки (`"9:16"`, `"9/16"`, число). Перекрывает дефолт 16/9 и вычисление из `width`/`height` |
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
├── readme.md                  # Этот файл (инструкция по использованию)
├── DEVELOPMENT.md             # Руководство по разработке и расширению
├── LICENSE                    # MIT
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
