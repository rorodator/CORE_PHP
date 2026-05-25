<?php
namespace Core\Rest\Lang;

use Core\Base\HttpMethod;
use Core\Base\RestService;
use Core\Base\SecurityLevel;

/**
 * Class LangGetService
 *
 * REST service for retrieving language labels.
 *
 * Endpoint: POST /api/lang/labels
 * Public — labels are not user-specific and may be loaded before login.
 */
class LangGetService extends RestService
{
    protected SecurityLevel $securityLevel = SecurityLevel::Public;

    protected ?HttpMethod $httpMethod = HttpMethod::Post;

    /** @var array<string, mixed> */
    protected array $security = [
        'auth'             => false,
        'public'           => true,
        'resource'         => 'lang',
        'resourceIdParam'  => null,
        'operation'        => 'read_labels',
        'visibilityAware'  => false,
    ];

    /**
     * Parameter specifications for this service.
     * @var array
     */
    protected $paramSpecs = [
        [
            'name' => 'lang',
            'type' => 'string',
            'required' => false,
            'regex' => '/^(fr|en|es|de)$/',
            'source' => 'json',
            'default' => 'fr'
        ],
        [
            'name' => 'key',
            'type' => 'string',
            'required' => false,
            'minLength' => 1,
            'source' => 'json'
        ]
    ];

    /**
     * POST /api/lang/labels - Get all labels for a language
     * POST /api/lang/labels - Get specific label
     * 
     * @return array Response with labels
     */
    protected function process()
    {
        $lang = $this->params['lang'] ?? 'fr';
        $key = $this->params['key'] ?? null;

        try {
            $langService = core()->lang;
            
            if ($key) {
                // Get specific label
                $label = $langService->getLabel($key, [], $lang);
                return [
                    'data' => [
                        'key' => $key,
                        'value' => $label,
                        'lang' => $lang
                    ],
                    'status' => 'SUCCESS'
                ];
            } else {
                // Get all labels
                $labels = $langService->getAllLabels($lang);
                return [
                    'data' => [
                        'labels' => $labels,
                        'lang' => $lang,
                        'availableLangs' => $langService->getAvailableLangs()
                    ],
                    'status' => 'SUCCESS'
                ];
            }
        } catch (\Exception $e) {
            return [
                'data' => null,
                'status' => 'LANG_ERROR'
            ];
        }
    }
}
