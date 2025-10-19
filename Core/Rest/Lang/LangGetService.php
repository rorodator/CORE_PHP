<?php
namespace Core\Rest\Lang;

use Core\Base\RestService;

/**
 * Class LangGetService
 *
 * REST service for retrieving language labels.
 * Handles only label retrieval (GET) operations.
 * 
 * Endpoints:
 * - POST /api/lang/labels - Get all labels for a language (lang in JSON body)
 * - POST /api/lang/labels - Get specific label (lang and key in JSON body)
 */
class LangGetService extends RestService
{
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
    protected function process($id = null)
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
?>
