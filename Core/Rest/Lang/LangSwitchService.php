<?php
namespace Core\Rest\Lang;

use Core\Base\RestService;

/**
 * Class LangSwitchService
 *
 * REST service for switching to the next available language.
 * Handles only language switching (GET) operations.
 * 
 * Endpoint:
 * - GET /api/lang/switch - Switch to next available language
 */
class LangSwitchService extends RestService
{
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
    protected function process($id = null)
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
?>
