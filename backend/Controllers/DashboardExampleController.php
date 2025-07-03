<?php

namespace SquareRouting\Controllers;

use SquareRouting\Core\Response;

class DashboardExampleController
{
    public function dashboardExample(?string $location): Response
    {
        // location for instance home, home/settings, etc.
        $availableSections = [
            'home' => 'Welcome to the Dashboard! This is your home page.',
            'settings' => 'Dashboard Settings. Adjust your preferences here.',
            'profile' => 'User Profile. View and edit your profile information.',
        ];

        $htmlContent = '';

        switch ($location) {
            case 'home':
                $htmlContent = "<h1>Welcome to the Dashboard!</h1><p>{$availableSections['home']}</p>";
                break;
            case 'settings':
                $htmlContent = "<h1>Dashboard Settings</h1><p>{$availableSections['settings']}</p>";
                break;
            case 'profile':
                $htmlContent = "<h1>User Profile</h1><p>{$availableSections['profile']}</p>";
                break;
            default:
                $htmlContent = '<h1>Dashboard Overview</h1><p>Available sections:</p><ul>';
                foreach ($availableSections as $key => $value) {
                    $htmlContent .= "<li><a href=\"/dashboard/{$key}\">" . ucfirst($key) . '</a></li>';
                }
                $htmlContent .= '</ul>';
                break;
        }

        return (new Response)->html($htmlContent);
    }
}
