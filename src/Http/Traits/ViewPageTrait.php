<?php

namespace Vis\Builder\Helpers\Traits;

use Vis\Builder\ViewPage;

trait ViewPageTrait
{
    public function setView()
    {
        ViewPage::create([
           'model' => get_class($this),
           'id_record' => $this->id,
        ]);
    }
}
