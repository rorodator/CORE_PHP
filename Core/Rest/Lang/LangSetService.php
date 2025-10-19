<?php
namespace Core\Rest\Lang;

use Core\Base\RestService;

/**
 * Class LangSetService
 *
 * REST service for setting the current language.
 * Handles only language setting (POST) operations.
 * 
 * Endpoint:
 * - POST /api/lang/set - Set current language for the session
 */
class LangSetService extends RestService
{
    /**
     * Parameter specifications for this service.
     * @var array
     */
    protected $paramSpecs = [
        [
            'name' => 'lang',
            'type' => 'string',
            'required' => true,
            'regex' => '/^(fr|en|es|de)$/',
            'source' => 'json'
        ]
    ];

    /**
     * POST /api/lang/set - Set current language for the session
     * 
     * @return array Response with new language
     */
    protected function process($id = null)
    {
        $lang = $this->params['lang'];

        try {
            $langService = core()->lang;
            
            if ($langService->setLang($lang)) {
                // Store language preference in session
                $session = core()->session;
                $session->set('user_lang', $lang);
                
                return [
                    'success' => true,
                    'data' => [
                        'lang' => $lang,
                        'message' => $langService->getLabel('notifications.language_changed', ['lang' => $langService->getLangDisplayName($lang)])
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'error' => [
                        'message' => 'Invalid language code',
                        'details' => "Language '$lang' is not supported"
                    ]
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => [
                    'message' => 'Failed to set language',
                    'details' => $e->getMessage()
                ]
            ];
        }
    }
}
?>
