<?php
namespace Core\Rest\Lang;

use Core\Base\RestService;

/**
 * Class LangCurrentService
 *
 * REST service for getting current language information.
 * Handles only current language retrieval (GET) operations.
 * 
 * Endpoint:
 * - GET /api/lang/current - Get current language and available languages
 */
class LangCurrentService extends RestService
{
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
    protected function process($id = null)
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
