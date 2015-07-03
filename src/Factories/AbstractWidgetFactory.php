<?php

namespace Arrilot\Widgets\Factories;

use Arrilot\Widgets\AbstractWidget;
use Arrilot\Widgets\Contracts\ApplicationWrapperContract;
use Arrilot\Widgets\Misc\InvalidWidgetClassException;
use Arrilot\Widgets\WidgetId;
use Illuminate\View\Expression;

abstract class AbstractWidgetFactory
{
    /**
     * Widget object to work with.
     *
     * @var AbstractWidget
     */
    protected $widget;

    /**
     * Widget configuration array.
     *
     * @var array
     */
    protected $widgetConfig;

    /**
     * The name of the widget being called.
     *
     * @var string
     */
    public $widgetName;

    /**
     * Array of widget parameters excluding the first one (config).
     *
     * @var array
     */
    public $widgetParams;

    /**
     * Array of widget parameters including the first one (config).
     *
     * @var array
     */
    public $widgetFullParams;

    /**
     * Laravel application wrapper for better testability.
     *
     * @var ApplicationWrapperContract;
     */
    public $app;

    /**
     * Another factory that produces some javascript.
     *
     * @var JavascriptFactory
     */
    protected $javascriptFactory;

    /**
     * The flag for not wrapping content in a special container.
     *
     * @var bool
     */
    public static $skipWidgetContainer = false;

    /**
     * Constructor.
     *
     * @param ApplicationWrapperContract $app
     */
    public function __construct(ApplicationWrapperContract $app)
    {
        $this->app = $app;

        $this->javascriptFactory = new JavascriptFactory($this);
    }

    /**
     * Magic method that catches all widget calls.
     *
     * @param string $widgetName
     * @param array  $params
     *
     * @return mixed
     */
    public function __call($widgetName, array $params = [])
    {
        array_unshift($params, $widgetName);

        return call_user_func_array([$this, 'run'], $params);
    }

    /**
     * Set class properties and instantiate a widget object.
     *
     * @param $params
     *
     * @throws InvalidWidgetClassException
     */
    protected function instantiateWidget(array $params = [])
    {
        WidgetId::increment();

        $this->widgetName = $this->parseFullWidgetNameFromString(array_shift($params));
        $this->widgetFullParams = $params;
        $this->widgetConfig = (array) array_shift($params);
        $this->widgetParams = $params;

        $rootNamespace = $this->app->config('arrilot-widget.defaultNamespace', $this->app->getNamespace().'Widgets');

        $widgetClass = class_exists($this->widgetName)
            ? $this->widgetName
            : $rootNamespace.'\\'.$this->widgetName;

        $widget = new $widgetClass($this->widgetConfig);
        if ($widget instanceof AbstractWidget === false) {
            throw new InvalidWidgetClassException();
        }

        $this->widget = $widget;
    }

    /**
     * Convert stuff like 'profile.feedWidget' to 'Profile\FeedWidget'.
     *
     * @param $widgetName
     *
     * @return string
     */
    protected function parseFullWidgetNameFromString($widgetName)
    {
        return studly_case(str_replace('.', '\\', $widgetName));
    }

    /**
     * Wrap the given content in a container if it's not an ajax call.
     *
     * @param $content
     *
     * @return string
     */
    protected function wrapContentInContainer($content)
    {
        if (self::$skipWidgetContainer) {
            return $content;
        }

        $container = $this->widget->container();
        if (empty($container['element'])) {
            $container['element'] = 'div';
        }

        return '<'.$container['element'].' id="'.$this->javascriptFactory->getContainerId().'" '.$container['attributes'].'>'.$content.'</'.$container['element'].'>';
    }

    /**
     * Final preparation for display.
     *
     * @param string $content
     * @return \Illuminate\View\Expression|string
     */
    protected function displayContent($content)
    {
        if (interface_exists('Illuminate\Contracts\Support\Htmlable') && class_exists('Illuminate\View\Expression'))
        {
            return new Expression($content);
        }

        return $content;
    }
}
