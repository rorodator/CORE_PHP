<?php
namespace Core\Rest\Lang;

use Core\Base\HttpMethod;
use Core\Base\RestService;
use Core\Base\SecurityLevel;

/**
 * Class LangCurrentService
 *
 * REST service for getting current language information.
 *
 * Endpoint: GET /api/lang/current
 * Public — language metadata is not user-sensitive.
 */
class LangCurrentService extends RestService
{
    protected SecurityLevel $securityLevel = SecurityLevel::Public;

    protected ?HttpMethod $httpMethod = HttpMethod::Get;

    /** @var array<string, mixed> */
    protected array $security = [
        'auth'             => false,
        'public'           => true,
        'resource'         => 'lang',
        'resourceIdParam'  => null,
        'operation'        => 'read_current_lang',
        'visibilityAware'  => false,
    ];

    /**
     * Parameter specifications for this service.
     * @var array
     */
    protected $paramSpecs = [];

    /**
     * GET /api/lang/current - Get current language and available languages
     * 
     * @return array Response with language information
     */
    protected function process()
    {
        try {
            $langService = core()->lang;
            $session = core()->session;
            
            // Try to get language from session first
            $sessionLang = $session->get('user_lang');
            if ($sessionLang && $langService->setLang($sessionLang)) {
                $currentLang = $sessionLang;
            } else {
                $currentLang = $langService->getCurrentLang();
            }

            return [
                'success' => true,
                'data' => [
                    'currentLang' => $currentLang,
                    'availableLangs' => $langService->getAvailableLangs(),
                    'displayNames' => [
                        'fr' => $langService->getLangDisplayName('fr'),
                        'en' => $langService->getLangDisplayName('en'),
                        'es' => $langService->getLangDisplayName('es'),
                        'de' => $langService->getLangDisplayName('de')
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => [
                    'message' => 'Failed to get language information',
                    'details' => $e->getMessage()
                ]
            ];
        }
    }
}
?>
