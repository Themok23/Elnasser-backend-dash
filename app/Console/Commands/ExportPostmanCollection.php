<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class ExportPostmanCollection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:postman {--base-url=http://localhost} {--api-v1-only : Export only API v1 endpoints}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export API endpoints to Postman collection';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $baseUrl = $this->option('base-url');
        $apiV1Only = $this->option('api-v1-only');
        
        if ($apiV1Only) {
            $this->info('Generating Postman collection for API v1 only...');
        } else {
            $this->info('Generating Postman collection...');
        }

        $routes = Route::getRoutes();
        $collection = $this->generatePostmanCollection($routes, $baseUrl, $apiV1Only);

        $outputPath = $apiV1Only 
            ? base_path('postman_collection_api_v1.json')
            : base_path('postman_collection.json');
            
        file_put_contents($outputPath, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Postman collection exported successfully to: {$outputPath}");
        $this->info("Total endpoints: " . $this->countEndpoints($collection));

        return 0;
    }

    /**
     * Generate Postman collection from routes
     */
    private function generatePostmanCollection($routes, $baseUrl, $apiV1Only = false)
    {
        $collection = [
            'info' => [
                'name' => $apiV1Only ? 'Elnasser Backend API V1' : 'Elnasser Backend API',
                'description' => $apiV1Only 
                    ? 'API v1 collection for Elnasser Backend' 
                    : 'Complete API collection for Elnasser Backend',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
                '_postman_id' => uniqid(),
            ],
            'variable' => [
                [
                    'key' => 'base_url',
                    'value' => $baseUrl,
                    'type' => 'string'
                ],
                [
                    'key' => 'auth_token',
                    'value' => '',
                    'type' => 'string'
                ],
                [
                    'key' => 'csrf_token',
                    'value' => '',
                    'type' => 'string'
                ]
            ],
            'item' => []
        ];

        $groupedRoutes = $this->groupRoutes($routes, $apiV1Only);
        
        foreach ($groupedRoutes as $groupName => $groupRoutes) {
            // Skip non-API v1 groups if api-v1-only is enabled
            if ($apiV1Only && $groupName !== 'API V1') {
                continue;
            }
            
            $folder = [
                'name' => $groupName,
                'item' => []
            ];

            foreach ($groupRoutes as $route) {
                $request = $this->createPostmanRequest($route, $baseUrl, $apiV1Only);
                if ($request) {
                    $folder['item'][] = $request;
                }
            }

            if (!empty($folder['item'])) {
                $collection['item'][] = $folder;
            }
        }

        return $collection;
    }

    /**
     * Group routes by their prefix/namespace
     */
    private function groupRoutes($routes, $apiV1Only = false)
    {
        $grouped = [
            'Web Routes' => [],
            'Admin Routes' => [],
            'Vendor Routes' => [],
            'API V1' => [],
            'API V2' => [],
        ];

        foreach ($routes as $route) {
            $uri = $route->uri();
            $methods = $route->methods();
            $action = $route->getAction();
            
            // Skip HEAD and OPTIONS methods
            $methods = array_filter($methods, function($method) {
                return !in_array($method, ['HEAD', 'OPTIONS']);
            });

            if (empty($methods)) {
                continue;
            }

            // Get action as string
            $actionString = null;
            if (isset($action['controller'])) {
                $actionString = $action['controller'];
            } elseif (isset($action['uses'])) {
                if (is_string($action['uses'])) {
                    $actionString = $action['uses'];
                } elseif (is_callable($action['uses'])) {
                    $actionString = 'Closure';
                }
            }

            $routeData = [
                'uri' => $uri,
                'methods' => $methods,
                'name' => $route->getName(),
                'action' => $actionString,
                'middleware' => $action['middleware'] ?? [],
            ];

            // Categorize routes
            if (strpos($uri, 'api/v1') === 0) {
                $grouped['API V1'][] = $routeData;
            } elseif (strpos($uri, 'api/v2') === 0) {
                $grouped['API V2'][] = $routeData;
            } elseif (strpos($uri, 'admin') === 0) {
                $grouped['Admin Routes'][] = $routeData;
            } elseif (strpos($uri, 'vendor-panel') === 0) {
                $grouped['Vendor Routes'][] = $routeData;
            } else {
                $grouped['Web Routes'][] = $routeData;
            }
        }

        return $grouped;
    }

    /**
     * Create Postman request from route
     */
    private function createPostmanRequest($routeData, $baseUrl, $apiV1Only = false)
    {
        $uri = $routeData['uri'];
        $methods = $routeData['methods'];
        $name = $routeData['name'] ?? $uri;
        $action = $routeData['action'];

        // Create a request for each HTTP method
        $requests = [];
        
        foreach ($methods as $method) {
            // Parse URI to handle route parameters
            $pathParts = [];
            $queryParams = [];
            $pathString = $uri;
            
            // Handle query parameters
            if (strpos($uri, '?') !== false) {
                list($pathString, $queryString) = explode('?', $uri, 2);
                parse_str($queryString, $queryParams);
            }
            
            // Split path and handle route parameters
            $pathSegments = explode('/', $pathString);
            foreach ($pathSegments as $segment) {
                if (!empty($segment)) {
                    // Convert {param} to :param for Postman
                    $segment = preg_replace('/\{(\w+)\}/', ':$1', $segment);
                    $pathParts[] = $segment;
                }
            }

            $request = [
                'name' => $name ?: $uri,
                'request' => [
                    'method' => $method,
                    'header' => $this->getDefaultHeaders($method, $apiV1Only),
                    'url' => [
                        'raw' => '{{base_url}}/' . $uri,
                        'host' => ['{{base_url}}'],
                        'path' => $pathParts,
                    ],
                    'description' => $this->generateDescription($routeData, $action),
                ],
                'response' => []
            ];
            
            // Add query parameters if any
            if (!empty($queryParams)) {
                $request['request']['url']['query'] = [];
                foreach ($queryParams as $key => $value) {
                    $request['request']['url']['query'][] = [
                        'key' => $key,
                        'value' => $value,
                        'disabled' => false
                    ];
                }
            }

            // Add body for POST, PUT, PATCH requests
            if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
                $request['request']['body'] = [
                    'mode' => 'raw',
                    'raw' => '{}',
                    'options' => [
                        'raw' => [
                            'language' => 'json'
                        ]
                    ]
                ];
            }

            // Add auth if needed (you can customize this based on middleware)
            if ($this->requiresAuth($routeData['middleware'])) {
                $request['request']['auth'] = [
                    'type' => 'bearer',
                    'bearer' => [
                        [
                            'key' => 'token',
                            'value' => '{{auth_token}}',
                            'type' => 'string'
                        ]
                    ]
                ];
            }

            $requests[] = $request;
        }

        // If multiple methods, create a folder, otherwise return single request
        if (count($requests) > 1) {
            return [
                'name' => $name ?: $uri,
                'item' => $requests
            ];
        }

        return $requests[0] ?? null;
    }

    /**
     * Get default headers for request
     */
    private function getDefaultHeaders($method, $apiV1Only = false)
    {
        $headers = [
            [
                'key' => 'Accept',
                'value' => 'application/json',
                'type' => 'text'
            ],
            [
                'key' => 'Content-Type',
                'value' => 'application/json',
                'type' => 'text'
            ]
        ];

        // Add CSRF token only for web routes (not API routes)
        if (!$apiV1Only && in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $headers[] = [
                'key' => 'X-CSRF-TOKEN',
                'value' => '{{csrf_token}}',
                'type' => 'text'
            ];
        }

        return $headers;
    }

    /**
     * Generate description for route
     */
    private function generateDescription($routeData, $action)
    {
        $description = [];
        
        if (!empty($routeData['name'])) {
            $description[] = "**Route Name:** `{$routeData['name']}`";
        }
        
        if ($action && is_string($action)) {
            $description[] = "**Controller:** `{$action}`";
        } elseif ($action) {
            $description[] = "**Controller:** Closure";
        }
        
        if (!empty($routeData['middleware'])) {
            $middleware = is_array($routeData['middleware']) 
                ? implode(', ', $routeData['middleware']) 
                : $routeData['middleware'];
            $description[] = "**Middleware:** `{$middleware}`";
        }

        return implode("\n\n", $description) ?: 'No description available';
    }

    /**
     * Check if route requires authentication
     */
    private function requiresAuth($middleware)
    {
        if (empty($middleware)) {
            return false;
        }

        $authMiddleware = ['auth', 'auth:api', 'admin', 'vendor', 'dm.api', 'vendor.api'];
        
        if (is_array($middleware)) {
            return !empty(array_intersect($authMiddleware, $middleware));
        }

        return in_array($middleware, $authMiddleware);
    }

    /**
     * Count total endpoints in collection
     */
    private function countEndpoints($collection)
    {
        $count = 0;
        
        foreach ($collection['item'] as $folder) {
            foreach ($folder['item'] as $item) {
                if (isset($item['item'])) {
                    // It's a folder with multiple methods
                    $count += count($item['item']);
                } else {
                    // Single request
                    $count++;
                }
            }
        }
        
        return $count;
    }
}

