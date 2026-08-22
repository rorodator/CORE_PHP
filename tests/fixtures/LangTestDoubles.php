<?php
/**
 * Lang REST service doubles for characterization tests.
 */

declare(strict_types=1);

namespace Core\Tests\Fixtures;

use Core\Rest\Lang\LangCurrentService;
use Core\Rest\Lang\LangGetService;
use Core\Rest\Lang\LangSetService;

class LangGetFrStub extends LangGetService
{
    /**
     * @return array<string, string>
     */
    protected function getJsonBody()
    {
        return ['lang' => 'fr'];
    }
}

class LangSetEnStub extends LangSetService
{
    /**
     * @return array<string, string>
     */
    protected function getJsonBody()
    {
        return ['lang' => 'en'];
    }
}

class LangCurrentStub extends LangCurrentService
{
}
