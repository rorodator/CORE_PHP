<?php
namespace Core\Rest\Lang;

use Core\Base\HttpMethod;
use Core\Base\RestService;
use Core\Base\SecurityLevel;

/**
 * Class LangSwitchService
 *
 * REST service for cycling through available languages.
 *
 * Endpoint: GET /api/lang/switch
 * Public — anonymous visitors may switch language.
 */
class LangSwitchService extends RestService
{
    protected SecurityLevel $securityLevel = SecurityLevel::Public;

    protected ?HttpMethod $httpMethod = HttpMethod::Get;

    /** @var array<string, mixed> */
    protected array $security = [
        'auth'             => false,
        'public'           => true,
        'resource'         => 'lang',
        'resourceIdParam'  => null,
        'operation'        => 'switch_session_lang',
        'visibilityAware'  => false,
    ];

    /**
     * Parameter specifications for this service.
     * @var array
     */
    protected $paramSpecs = [];

    /**
     * GET /api/lang/switch - Switch to next available language
     * 
     * @return array Response with new language
     */
    protected function process()
    {
        try {
            $langService = core()->lang;
            $newLang = $langService->switchToNextLang();
            
            // Store language preference in session
            $session = core()->session;
            $session->set('user_lang', $newLang);
            
            return [
                'success' => true,
                'data' => [
                    'lang' => $newLang,
                    'displayName' => $langService->getLangDisplayName($newLang),
                    'message' => $langService->getLabel('notifications.language_switched', ['lang' => $langService->getLangDisplayName($newLang)])
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => [
                    'message' => 'Failed to switch language',
                    'details' => $e->getMessage()
                ]
            ];
        }
    }
}
