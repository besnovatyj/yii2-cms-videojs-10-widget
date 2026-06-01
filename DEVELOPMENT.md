# DEVELOPMENT.md — Руководство по разработке и расширению виджета

## Архитектура и принципы работы

### Ключевое изменение VideoJS v8 → v10

VideoJS v10 — это полный переворот архитектуры:

| Аспект              | v8 (старый)                         | v10 (новый)                        |
|---------------------|-------------------------------------|------------------------------------|
| **Пакет npm**       | `video.js`                          | `@videojs/html`                    |
| **HTML элемент**    | `<video class="video-js">`          | `<video-player><video-skin><video>`|
| **Инициализация JS**| `videojs('id', { options })`        | Автоматическая (Web Components)    |
| **data-setup**      | JSON атрибут на video               | Не нужен — Web Components сами     |
| **CSS классы**      | `video-js vjs-fluid vjs-big-play-centered` | CSS скин из `@videojs/html/video/skin.css` |
| **Плагины**         | `videojs.plugin()`, `videojs.registerComponent()` | Переписаны, v8 плагины не работают |
| **TypeScript**      | `.d.ts` файлы в npm пакете          | Нативная TS поддержка              |
| **Бандл размер**    | ~670 KB (video.min.js)              | ~70-80% меньше благодаря tree-shaking |

### Почему два режима (VideoJS + Iframe)?

- **VideoJS v10** отлично работает с локальными файлами (MP4, WebM, HLS), но плагины для YouTube/Rutube/VK на v10 ещё не стабилизированы (beta на апрель 2026)
- Модуль актёров `yii2-cms-person` уже вычисляет `iframe_url` для всех провайдеров — это готовые данные для iframe встройки
- Iframe — нативный подход без зависимостей от сторонних VideoJS плагинов

### Поток данных (iframe режим)

```
PersonVideo::$iframe_url ──→ Player::$iframeSrc
PersonVideo::$iframe_allow ──→ Player::$iframeAllow
PersonVideo::$iframe_referrerpolicy ──→ Player::$iframeReferrerPolicy
PersonVideo::$thumbnail_url ──→ Player::$poster
                                      │
                          ┌───────────▼──────────────┐
                          │ lazyIframe = true         │
                          │ div.vjs-iframe-embed       │
                          │ data-src="iframe_url"      │
                          │ background: poster         │
                          └───────────┬──────────────┘
                                      │ клик пользователя
                          ┌───────────▼──────────────┐
                          │ TypeScript handleIframeClick()│
                          │ createElement('iframe')    │
                          │ iframe.src = url + autoplay│
                          └──────────────────────────┘
```

### Поток данных (VideoJS режим)

```
[source src="/v.mp4" type="video/mp4"]  ←── шорткод (content property)
              │
    parseShortcodeContent() [PHP]
              │
    $sources = [['src' => '/v.mp4', 'type' => 'video/mp4']]
              │
    renderVideoJs() [PHP]
              │
    <video-player>        ←── PHP генерирует HTML
      <video-skin>
        <video>
          <source>
        </video>
      </video-skin>
    </video-player>
              │
    @videojs/html [TypeScript/npm]
              │
    CustomElementRegistry.define('video-player', ...)
    Браузер автоматически инициализирует плеер
```

---

## Структура TypeScript кода (`src/ts/index.ts`)

```
index.ts
  ├── import '@videojs/html'           // Регистрирует Web Components
  ├── import '@videojs/html/video/skin.css'  // VideoJS стили
  ├── import './styles.css'            // Overlay стили
  │
  ├── initIframeEmbeds()               // Entry point (DOM ready)
  │     └── forEach .vjs-iframe-embed
  │           ├── addEventListener('click', handleIframeClick)
  │           └── addEventListener('keydown', ...)
  │
  └── handleIframeClick(embed)         // Click handler
        ├── Читает data-src, data-allow, data-referrerpolicy
        ├── Добавляет ?autoplay=1 к URL
        ├── Создаёт <iframe> элемент
        └── Заменяет overlay на iframe
```

---

## Как расширять виджет

### 1. Добавить поддержку субтитров (tracks)

В `Player.php` добавить свойство:

```php
/**
 * Субтитры/дорожки для локального видео.
 * Формат: [['kind' => 'subtitles', 'src' => '/subs/ru.vtt', 'srclang' => 'ru', 'label' => 'Русский']]
 * @var array[]
 */
public array $tracks = [];
```

В `renderVideoJs()` добавить после `$sourcesHtml`:

```php
$tracksHtml = '';
foreach ($this->tracks as $track) {
    $tracksHtml .= "\n        " . Html::tag('track', '', $track);
}

$videoHtml = Html::tag('video', $sourcesHtml . $tracksHtml . "\n    ", $videoAttrs);
```

В `parseShortcodeContent()` добавить парсинг `[track ...]`:

```php
preg_match_all('/\[track\s+([^]]+)]/', $this->content, $trackMatches);
foreach ($trackMatches[1] as $attrString) {
    $attrs = $this->parseSourceAttributes($attrString);
    if (!empty($attrs)) {
        $this->tracks[] = $attrs;
    }
}
```

Шорткод с субтитрами:
```
[videojs, poster="/img/poster.jpg"]
  [source src="/video.mp4" type="video/mp4"]
  [track kind="subtitles" src="/subs/ru.vtt" srclang="ru" label="Русский" default=1]
[/videojs]
```

---

### 2. Добавить кастомный скин VideoJS v10

VideoJS v10 поддерживает несколько скинов. Для кастомизации:

1. Создать `src/ts/custom-skin.css`
2. Импортировать в `index.ts`: `import './custom-skin.css'`
3. Переопределить CSS переменные VideoJS v10:

```css
/* src/ts/custom-skin.css */
video-player {
    --vjs-color-primary: #e31c25;    /* Основной цвет (прогресс, кнопки) */
    --vjs-color-secondary: #fff;     /* Вторичный цвет */
}
```

---

### 3. Добавить плейлист (несколько видео)

Создать новый виджет `Playlist.php`:

```php
namespace Besnovatyj\VideoJs;

use yii\base\Widget;

class Playlist extends Widget
{
    /** @var array[] Список видео: [['sources' => [...], 'poster' => '...', 'title' => '...'], ...] */
    public array $videos = [];

    public function run(): string
    {
        $html = '';
        foreach ($this->videos as $video) {
            $html .= Player::widget($video);
        }
        return $html;
    }
}
```

---

### 4. Добавить поддержку HLS/DASH потоков

VideoJS v10 нативно поддерживает HLS (через hls.js). Для MP4 ничего не нужно.

Для HLS добавить в `$sources`:
```php
'sources' => [
    ['src' => 'https://example.com/stream.m3u8', 'type' => 'application/x-mpegURL'],
],
```

Для DASH — потребуется плагин (ждём стабилизации VideoJS v10 плагинов).

---

### 5. Добавить аналитику (клики, время просмотра)

В `src/ts/index.ts` добавить после инициализации `@videojs/html`:

```typescript
// Отслеживание событий на video-player
document.querySelectorAll('video-player').forEach((playerEl) => {
    const video = playerEl.querySelector('video');
    if (!video) return;

    video.addEventListener('play', () => {
        // Отправить аналитику
        console.log('Video started', video.currentSrc);
    });

    video.addEventListener('ended', () => {
        console.log('Video ended', video.currentSrc);
    });
});
```

---

### 6. Lazy loading для VideoJS (Intersection Observer)

Для улучшения производительности — инициализировать VideoJS только когда плеер в viewport:

```typescript
// В index.ts, вместо прямого import '@videojs/html'
const observer = new IntersectionObserver((entries) => {
    entries.forEach(async (entry) => {
        if (entry.isIntersecting) {
            // Динамический import (code splitting)
            await import('@videojs/html');
            observer.unobserve(entry.target);
        }
    });
});

document.querySelectorAll('video-player').forEach((el) => observer.observe(el));
```

Добавить в `esbuild.config.mjs`:
```javascript
splitting: true,      // Включить code splitting
format: 'esm',        // Только ESM поддерживает dynamic import
```

И изменить `PlayerAssets.php`:
```php
public $jsOptions = ['type' => 'module']; // ESM требует type=module
```

---

### 7. Добавить поддержку VK Video через VideoJS

Когда появится стабильный VideoJS v10 плагин для VK:

1. `npm install videojs-vk` (гипотетически)
2. В `index.ts`: `import 'videojs-vk'`
3. В `Player.php` добавить определение типа провайдера:

```php
/** Тип провайдера: 'local', 'youtube', 'vimeo', 'vk', 'rutube' */
public string $providerType = 'local';
```

4. В `renderVideoJs()` добавить условный data-setup для VK провайдера.

---

## Диагностика проблем

### `<video-player>` не работает (нет интерфейса плеера)

**Причина:** `player.js` не содержит `@videojs/html` (не выполнена сборка npm).

**Решение:**
```bash
npm install && npm run build
```

Проверить в DevTools что `player.js` содержит строку `CustomElementRegistry` или `customElements.define`.

---

### Iframe не открывается по клику

**Причина 1:** `data-src` атрибут не задан в HTML.

Проверить в DevTools:
```javascript
document.querySelector('.vjs-iframe-embed').dataset.src
// Должно вернуть URL, не undefined
```

**Причина 2:** `player.js` не загружен или загружен с ошибкой.

Проверить в DevTools > Network > player.js.

---

### Autoplay не работает для iframe

**Причина:** Браузерная политика autoplay. YouTube, Rutube и другие требуют взаимодействия пользователя.

Click-to-play решает эту проблему — пользователь кликает, мы добавляем `?autoplay=1`, и браузер разрешает autoplay т.к. это было пользовательское действие.

---

### Ошибка `InvalidConfigException` в PHP

**Причина:** Не задан ни `$iframeSrc`, ни `$sources`.

Проверить что хотя бы одно из условий выполнено:
```php
// Вариант 1: iframeSrc
'iframeSrc' => 'https://...'

// Вариант 2: sources
'sources' => [['src' => '/video.mp4', 'type' => 'video/mp4']]

// Вариант 3: шорткод с [source ...]
'content' => '[source src="/video.mp4" type="video/mp4"]'
```

---

## Timeline VideoJS v10

- **30 октября 2025** — Technology Preview
- **Февраль 2026** — Beta (текущая версия)
- **Середина 2026** — General Availability (GA)
- **Конец 2026** — Полная совместимость и миграционные инструменты с v8

После GA рекомендуется обновить `@videojs/html` до стабильной версии и проверить изменения в API.

Следить за изменениями: https://github.com/videojs/v10/
