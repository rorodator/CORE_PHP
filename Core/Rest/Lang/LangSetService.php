<?php
namespace Core\Rest\Lang;

use Core\Base\HttpMethod;
use Core\Base\RestService;
use Core\Base\SecurityLevel;

/**
 * Class LangSetService
 *
 * REST service for setting the current language for the session.
 *
 * Endpoint: POST /api/lang/set
 * Public — anonymous visitors may pick their language before signing up.
 */
class LangSetService extends RestService
{
    protected SecurityLevel $securityLevel = SecurityLevel::Public;

    protected ?HttpMethod $httpMethod = HttpMethod::Post;

    /** @var array<string, mixed> */
    protected array $security = [
        'auth'             => false,
        'public'           => true,
        'resource'         => 'lang',
        'resourceIdParam'  => null,
        'operation'        => 'set_session_lang',
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
    protected function process()
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
