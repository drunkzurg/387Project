<?php

function frontendAssetsRender(string $entry = 'frontend/src/main.tsx'): string
{
    $manifestPath = __DIR__ . '/../../public/assets/build/.vite/manifest.json';

    if (!is_file($manifestPath)) {
        return implode("\n", [
            '<link rel="stylesheet" href="assets/css/app.css">',
            '<script type="module" src="assets/js/app.js"></script>',
        ]);
    }

    $manifest = json_decode((string)file_get_contents($manifestPath), true);
    if (!is_array($manifest) || !isset($manifest[$entry]) || !is_array($manifest[$entry])) {
        return '';
    }

    $asset = $manifest[$entry];
    $tags = [];

    foreach (($asset['css'] ?? []) as $cssFile) {
        $tags[] = sprintf(
            '<link rel="stylesheet" href="%s">',
            htmlspecialchars('assets/build/' . (string)$cssFile, ENT_QUOTES, 'UTF-8')
        );
    }

    if (isset($asset['file'])) {
        $tags[] = sprintf(
            '<script type="module" src="%s"></script>',
            htmlspecialchars('assets/build/' . (string)$asset['file'], ENT_QUOTES, 'UTF-8')
        );
    }

    return implode("\n", $tags);
}

function frontendJsonScript(string $id, array $payload): string
{
    $json = json_encode(
        $payload,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
    );

    return sprintf(
        '<script type="application/json" id="%s">%s</script>',
        htmlspecialchars($id, ENT_QUOTES, 'UTF-8'),
        $json
    );
}
