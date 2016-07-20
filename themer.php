<?php
/**
 * Themer v1.1.0
 *
 * This plugin enables you to use different themes on one site
 * individual set per page or collection.
 *
 * Dual licensed under the MIT or GPL Version 3 licenses, see LICENSE.
 * http://benjamin-regler.de/license/
 *
 * @package     Themer
 * @version     1.1.0
 * @link        <https://github.com/sommerregen/grav-plugin-themer>
 * @author      Benjamin Regler <sommerregen@benjamin-regler.de>
 * @copyright   2015-2016, Benjamin Regler
 * @license     <http://opensource.org/licenses/MIT>        MIT
 * @license     <http://opensource.org/licenses/GPL-3.0>    GPLv3
 */

namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Page\Page;
use Grav\Common\Data\Blueprints;

use RocketTheme\Toolbox\Event\Event;

/**
 * ThemerPlugin
 *
 * This plugin enables you to use different themes on one site individual
 * set per page or collection.
 */
class ThemerPlugin extends Plugin
{
    /**
     * Return a list of subscribed events of this plugin.
     *
     * @return array    The list of events of the plugin of the form
     *                      'name' => ['method_name', priority].
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0]
        ];
    }

    /**
    * Initialize configuration
    */
    public function onPluginsInitialized()
    {
        $events = [
            'onPageInitialized' => ['onPageInitialized', 1000]
        ];

        // Set admin specific events
        if ($this->isAdmin()) {
            $this->active = false;
            $events = [
                'onBlueprintCreated' => ['onBlueprintCreated', 0]
            ];
        }

        // Register events
        $events['onGetPageTemplates'] = ['onGetPageTemplates', 0];
        $this->enable($events);
    }

    /**
     * Change theme of page
     */
    public function onPageInitialized()
    {
        /** @var Page $page */
        $page = $this->grav['page'];

        $config = $this->mergeConfig($page);
        if ($config->get('enabled') && ($theme = $this->mergeThemeConfig($page))) {
            if ($theme !== $this->config->get('system.pages.theme')) {
                // Update system configurations
                $this->config->set('system.pages.theme', $theme);

                // Reload themes to reflect changes
                $this->grav['themes']->init();

                // Reset and re-initialize Twig environment
                $twig = $this->grav['twig'];
                $twig->twig = null;
                $twig->twig_paths = [];
                $twig->init();
            }
        }
    }

    /**
     * Add page template types.
     */
    public function onGetPageTemplates(Event $event)
    {
        /** @var Types $types */
        $types = $event->types;

        /** @var Locator $locator */
        $locator = $this->grav['locator'];

        // Add theme templates to list
        $templates = $this->config->get('plugins.themer.templates', '');
        if ($templates && !is_array($templates)) {
            $templates = explode(', ', $templates);
        }

        $templates = $templates ?: array_keys($this->grav['themes']->all());
        foreach ($templates as $template) {
            $template = strtolower($template);
            if ($path = $locator->findResource("themes://{$template}/templates/")) {
                $types->scanTemplates($path);
            }
        }
    }

    /**
     * Extend page blueprints with ArchivePlus configuration options.
     *
     * @param Event $event
     */
    public function onBlueprintCreated(Event $event)
    {
        /** @var Blueprints $blueprint */
        $blueprint = $event['blueprint'];
        if ($blueprint->get('form/fields/tabs')) {
            $blueprints = new Blueprints(__DIR__ . '/blueprints/');
            $extends = $blueprints->get($this->name);
            $blueprint->extend($extends, true);
        }
    }

    /**
     * Merge global and page theme settings
     *
     * @param Page  $page    The page to merge the page theme configurations
     *                       with the theme settings.
     * @param bool  $default The default value in case no theme setting was
     *                       found.
     *
     * @return array
     */
    protected function mergeThemeConfig(Page $page, $default = null)
    {
        while ($page && !$page->root()) {
            if (isset($page->header()->theme)) {
                $theme = $page->header()->theme;
                if ($theme === '@default') {
                    $theme = $default;
                }

                return $theme;
            }
            $page = $page->parent();
        }

        return $default;
    }
}
