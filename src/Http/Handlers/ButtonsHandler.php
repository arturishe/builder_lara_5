<?php

namespace Vis\Builder\Handlers;

class ButtonsHandler
{
    protected $def;
    protected $controller;

    public function __construct(array $exportDefinition, &$controller)
    {
        $this->def = $exportDefinition;
        $this->controller = $controller;
    }

    public function fetch()
    {
        $buttons = $this->def;

        if (! count($buttons)) {
            return;
        }

        $buttonsHtml = '';

        foreach ($buttons as $button) {
            $buttonsHtml .= $this->checkShowButton($button) ? '' : view('admin::tb.button', compact('button'));
        }

        return $buttonsHtml;
    }

    private function checkShowButton($button)
    {
        return ! $button || ! $button['check']();
    }
}
