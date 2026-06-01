<?php


/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\VideoJs;

use yii\web\AssetBundle;

/**
 * Asset bundle для VideoJS v10 виджета.
 *
 * Подключает:
 * - player.css: стили VideoJS v10 скина + overlay для iframe click-to-play
 * - player.js: VideoJS v10 Web Components + логика iframe click-to-play
 *
 * Файлы собираются командой:
 * ```bash
 * cd app/packages/besnovatyj/yii2-cms-videojs-10-widget
 * npm install && npm run build
 * ```
 *
 * До сборки player.css содержит только overlay стили (iframe режим работает).
 * После сборки player.js включает @videojs/html (VideoJS v10 Web Components).
 */
class PlayerAssets extends AssetBundle
{
    /**
     * Директория с собранными assets.
     * Yii2 публикует файлы из этой директории в web/assets/.
     */
    public $sourcePath = __DIR__ . '/media';

    /**
     * CSS файлы.
     * После npm run build включает стили VideoJS v10 + overlay.
     */
    public $css = [
        'player.css',
    ];

    /**
     * JS файлы.
     * После npm run build включает @videojs/html (Web Components) + iframe click-to-play.
     */
    public $js = [
        'player.js',
    ];
}
