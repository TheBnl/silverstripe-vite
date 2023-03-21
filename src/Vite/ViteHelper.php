<?php

namespace ViteHelper\Vite;

use PharIo\Manifest\Requirement;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\View\Requirements;
use SilverStripe\View\ViewableData;

/**
 * Usage:
 *
 * Call the Tags in the template.ss:
 *
 * These will figure out if your application is in Dev mode, depending on the isDev() method.
 * $Vite.HeaderTags.RAW
 * $Vite.BodyTags.RAW
 *
 * If $forceProductionMode is set to true, or a URL-param ?vprod is set,
 * production versions will be served.
 *
 * Or decide on your own:
 *
 * Dev-tags (available while vite dev-server is running):
 * $Vite.ClientScripts.RAW - Header
 * $Vite.DevScripts.RAW - after Body
 *
 * Production Tags (available after running vite build):
 * $Vite.CSS.RAW
 * $Vite.JS.RAW
 */
class ViteHelper extends ViewableData
{
    use Configurable;

    /**
     * Disable dev scripts and serve the production files.
     */
    private static bool $force_production_mode = false;

    /**
     * Port where the ViteJS dev server will serve
     */
    private static int $dev_port = 5173;

    /**
     * Source directory for .js/.ts/.vue/.scss etc.
     */
    private static string $js_src_directory = 'client/src';

    /**
     * Main js / ts file.
     */
    private static string $main_js = 'main.js';

    /**
     * Relative path (from /public) to the manifest.json created by ViteJS after running the build command.
     */
    private static string $manifest_path = '/app/client/dist/manifest.json';

    public function initVite()
    {
        // echo '<pre>';
        // print_r($this->isDev() ? 'is dev' : 'not dev');
        // echo '</pre>';
        // exit();
        // if ($this->isDev()) {

        // }

        Requirements::insertHeadTags($this->getHeaderTags(), 'vite_head');
        Requirements::insertHeadTags($this->getBodyTags(), 'vite_body');
        

        echo '<pre>';
        print_r($this->getManifest());
        print_r("\n\getHeaderTags\n");
        print_r($this->getHeaderTags());
        print_r("\n\ngetBodyTags\n");
        print_r($this->getBodyTags());
        echo '</pre>';
        exit();

        
    }

    /**
     * Serve script tags for insertion in the HTML head,
     * either for dev od production, depending on the isDev() method.
     */
    public function getHeaderTags(): string
    {
        return $this->isDev() ? $this->getClientScript() : $this->getCSS();
    }

    /**
     * Serve script tags for insertion at the end of HTML body,
     * either for dev od production, depending on the isDev() method.
     */
    public function getBodyTags(): string
    {
        return $this->isDev() ? $this->getDevScript() : $this->getJS();
    }

    /**
     * For production. Available after build.
     * Return the css files created by ViteJS
     */
    public function getCSS(): string
    {
        $manifest = $this->getManifest();
        if (!$manifest) {
            return '';
        }

        $style_tags = [];
        foreach ($manifest as $item) {

            if (!empty($item->isEntry) && true === $item->isEntry && !empty($item->css)) {

                foreach ($item->css as $css_path) {
                    $style_tags[] = $this->css($css_path);
                }
            }
        }

        return implode("\n", $style_tags);
    }

    /**
     * For production. Available after build.
     * Return the most recent js file created by ViteJS
     *
     * Will return additional <script nomodule> tags
     * if @vite/plugin-legacy is installed.
     */
    public function getJS(): string
    {
        $manifest = $this->getManifest();
        if (!$manifest) {
            return '';
        }

        $script_tags = [];
        foreach ($manifest as $item) {

            if (!empty($item['isEntry'])) {

                $params = [];
                if (strpos($item['src'], 'legacy') !== false) {
                    $params[] = 'nomodule';
                } else {
                    $params['type'] = 'module';
                }

                $script_tags[] = $this->script($item['file'], $params);
            }
        }

        /**
         * Legacy Polyfills must come first.
         */
        usort($script_tags, function ($tag) {
            return strpos($tag, 'polyfills') !== false ? 0 : 1;
        });

        /**
         * ES-module scripts must come last.
         */
        usort($script_tags, function ($tag) {
            return strpos($tag, 'type="module"') !== false ? 1 : 0;
        });

        return implode("\n", $script_tags);
    }

    /**
     * For dev mode at the end of HTML body.
     */
    public function getDevScript(): string
    {
        $port = self::config()->get('dev_port');
        return $this->script("http://localhost:{$port}/@vite/client", [
            'type' => 'module',
        ]);
    }

    /**
     * For dev mode in HTML head.
     */
    public function getClientScript(): string
    {
        $port = self::config()->get('dev_port');
        $dir = self::config()->get('js_src_directory');
        $script = self::config()->get('main_js');
        return $this->script("http://localhost:{$port}/{$dir}/{$script}", [
            'type' => 'module',
        ]);
    }

    /**
     * Get data on the files created by ViteJS
     * from /public/manifest.json
     */
    private function getManifest(): ?array
    {
        // TODO: add cache
        $root = Director::baseFolder();
        if (!$root) {
            return null;
        }

        $path = $root . self::config()->get('manifest_path');

        if (!file_exists($path)) {
            throw new \Exception('Could not find manifest.json at ' . $path);
        }

        $manifest = file_get_contents($path);
        $manifest = mb_convert_encoding($manifest, 'utf8');
        if (!$manifest) {
            throw new \Exception('No ViteDataExtension manifest.json found. ');
        }

        $manifest = str_replace([
            "\u0000",
        ], '', $manifest);

        return json_decode($manifest, true);
    }

    /**
     * Decide what files to serve.
     * If forceProductionMode is set to true or when in dev mode
     * @todo check if we can detect vite dev is active
     */
    private function isDev(): bool
    {
        if (self::config()->get('force_production_mode') === true) {
            return false;
        }

        return false;

        return Director::isDev();
    }

    private function css(string $url): string
    {
        return '<link rel="stylesheet" href="' . $url . '">';
    }

    private function script(string $url, array $params = []): string
    {
        $params_string = "";
        foreach ($params as $param => $value) {

            if (is_int($param)) {
                $params_string .= sprintf('%s ', $value);
                continue;
            }

            $params_string .= sprintf('%s="%s" ', $param, $value);
        }

        $script = '<script src="' . $url . '" ' . $params_string . '></script>';
        return $script;
    }
}
