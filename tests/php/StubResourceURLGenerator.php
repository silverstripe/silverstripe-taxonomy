<?php

namespace SilverStripe\Taxonomy\Tests;

use SilverStripe\Core\Manifest\ModuleResource;
use SilverStripe\Core\Manifest\ResourceURLGenerator;

class StubResourceURLGenerator implements ResourceURLGenerator
{
    public function urlForResource($resource)
    {
        return '/' . ($resource instanceof ModuleResource ? $resource->getRelativePath() : $resource);
    }
}
