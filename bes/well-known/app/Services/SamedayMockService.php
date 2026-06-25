<?php
namespace App\Services;

use Illuminate\Support\Facades\Log;

class SamedayMockService
{
    public function __construct()
    {
        Log::info('Using enhanced mock Sameday service for development');
    }
    
    public function getPickupPoints($city = null)
    {
        // Enhanced sample data with more pickup points
        $allPickupPoints = [
            [
                'id' => 1,
                'sameday_id' => 12345,
                'name' => 'Pickup Point Bucharest Center',
                'county' => [
                    'id' => 1,
                    'name' => 'Bucharest',
                    'code' => 'B'
                ],
                'city' => [
                    'id' => 1,
                    'name' => 'Bucharest',
                    'samedayId' => 1001
                ],
                'address' => 'Calea Victoriei 12, Bucharest',
                'lat' => 44.4268,
                'lng' => 26.1025,
                'default' => true
            ],
            [
                'id' => 2,
                'sameday_id' => 12346,
                'name' => 'Pickup Point Cluj Main',
                'county' => [
                    'id' => 2,
                    'name' => 'Cluj',
                    'code' => 'CJ'
                ],
                'city' => [
                    'id' => 2,
                    'name' => 'Cluj-Napoca',
                    'samedayId' => 1002
                ],
                'address' => 'Str. Memorandumului 5, Cluj-Napoca',
                'lat' => 46.7693,
                'lng' => 23.5899,
                'default' => false
            ],
            [
                'id' => 3,
                'sameday_id' => 12347,
                'name' => 'TM Work Point Timisoara Str',
                'county' => [
                    'id' => 3,
                    'name' => 'Timis',
                    'code' => 'TM'
                ],
                'city' => [
                    'id' => 3,
                    'name' => 'Timisoara',
                    'samedayId' => 1003
                ],
                'address' => 'Bulevardul Vasile Pârvan 4, Timisoara',
                'lat' => 45.7489,
                'lng' => 21.2275,
                'default' => false
            ],
            [
                'id' => 4,
                'sameday_id' => 12348,
                'name' => 'Cluj Secondary Hub',
                'county' => [
                    'id' => 2,
                    'name' => 'Cluj',
                    'code' => 'CJ'
                ],
                'city' => [
                    'id' => 2,
                    'name' => 'Cluj-Napoca',
                    'samedayId' => 1002
                ],
                'address' => 'Strada Avram Iancu 442-446, Cluj-Napoca',
                'lat' => 46.7772,
                'lng' => 23.6214,
                'default' => false
            ]
        ];
        
        // Filter by city if specified
        if ($city !== null) {
            $filteredPoints = array_filter($allPickupPoints, function($point) use ($city) {
                return strcasecmp($point['city']['name'], $city) === 0;
            });
            
            $points = array_values($filteredPoints);
        } else {
            $points = $allPickupPoints;
        }
        
        return [
            'success' => true,
            'data' => $points
        ];
    }
    
  public function getServices()
{
    // Complete mock service types matching the screenshot
    return [
        'success' => true,
        'data' => [
            [
                'id' => 1,
                'code' => '24H',
                'name' => '24H',
                'description' => 'Next day delivery by 6 PM',
                'serviceType' => 'Delivery'
            ],
            [
                'id' => 2,
                'code' => 'RETURN_STD',
                'name' => 'Return Standard',
                'description' => 'Standard return service',
                'serviceType' => 'Return'
            ],
            [
                'id' => 3,
                'code' => 'LOCKER_ND',
                'name' => 'Locker NextDay',
                'description' => 'Next day delivery to locker',
                'serviceType' => 'Locker'
            ],
            [
                'id' => 4,
                'code' => 'LOCKER_HD',
                'name' => 'Locker Home Delivery',
                'description' => 'Home delivery from locker',
                'serviceType' => 'Locker'
            ],
            [
                'id' => 5,
                'code' => 'PARCEL_EXCHANGE',
                'name' => 'Parcel for exchange',
                'description' => 'Exchange parcel service',
                'serviceType' => 'Exchange'
            ],
            [
                'id' => 6,
                'code' => 'RETURN_DOCS',
                'name' => 'Return Documents',
                'description' => 'Document return service',
                'serviceType' => 'Return'
            ],
            [
                'id' => 7,
                'code' => 'RETURN_LOCKER',
                'name' => 'Return Locker',
                'description' => 'Return to locker service',
                'serviceType' => 'Return'
            ],
            [
                'id' => 8,
                'code' => 'CB_HD_24H',
                'name' => 'Crossborder HD 24H',
                'description' => 'Cross-border home delivery 24H',
                'serviceType' => 'CrossBorder'
            ],
            [
                'id' => 9,
                'code' => 'CB_RETURN',
                'name' => 'Cross-border Return standard',
                'description' => 'Standard cross-border return',
                'serviceType' => 'CrossBorder'
            ],
            [
                'id' => 10,
                'code' => 'CB_LOCKER',
                'name' => 'Crossborder Locker delivery',
                'description' => 'Cross-border locker delivery',
                'serviceType' => 'CrossBorder'
            ],
            [
                'id' => 11,
                'code' => 'CB_LOCKER_RETURN',
                'name' => 'Crossborder Locker return',
                'description' => 'Cross-border locker return',
                'serviceType' => 'CrossBorder'
            ],
            [
                'id' => 12,
                'code' => 'CB_LOCKER_HD',
                'name' => 'Crossborder Locker HD',
                'description' => 'Cross-border locker home delivery',
                'serviceType' => 'CrossBorder'
            ],
            [
                'id' => 13,
                'code' => 'EASYBOX_ND',
                'name' => 'EasyBox NextDay',
                'description' => 'Next day EasyBox delivery',
                'serviceType' => 'EasyBox'
            ],
            [
                'id' => 14,
                'code' => 'CB_PUDO_DEL',
                'name' => 'Crossborder Pudo Delivery',
                'description' => 'Cross-border PUDO delivery',
                'serviceType' => 'CrossBorder'
            ],
            [
                'id' => 15,
                'code' => 'PUDO_HD',
                'name' => 'PUDO Home Delivery',
                'description' => 'PUDO home delivery',
                'serviceType' => 'PUDO'
            ],
            [
                'id' => 16,
                'code' => 'CB_PUDO_HD',
                'name' => 'Crossborder Pudo HD',
                'description' => 'Cross-border PUDO home delivery',
                'serviceType' => 'CrossBorder'
            ],
            [
                'id' => 17,
                'code' => 'H2P',
                'name' => 'Home to Pudo',
                'description' => 'Home to PUDO delivery',
                'serviceType' => 'PUDO'
            ],
            [
                'id' => 18,
                'code' => 'H2P_XB',
                'name' => 'Home to Pudo XB',
                'description' => 'Home to PUDO cross-border',
                'serviceType' => 'PUDO'
            ],
            [
                'id' => 19,
                'code' => 'REDIRECT_L2P',
                'name' => 'Redirect Locker2Pudo',
                'description' => 'Redirect from locker to PUDO',
                'serviceType' => 'Redirect'
            ],
            [
                'id' => 20,
                'code' => 'REDIRECT_L2P_XB',
                'name' => 'Redirect Locker2Pudo XB',
                'description' => 'Redirect from locker to PUDO cross-border',
                'serviceType' => 'Redirect'
            ]
        ]
    ];
}
    public function getCities($county = null)
    {
        // Mock cities data
        $allCities = [
            [
                'id' => 1,
                'name' => 'Bucharest',
                'county' => [
                    'id' => 1,
                    'name' => 'Bucharest',
                    'code' => 'B'
                ],
                'samedayId' => 1001
            ],
            [
                'id' => 2,
                'name' => 'Cluj-Napoca',
                'county' => [
                    'id' => 2,
                    'name' => 'Cluj',
                    'code' => 'CJ'
                ],
                'samedayId' => 1002
            ],
            [
                'id' => 3,
                'name' => 'Timisoara',
                'county' => [
                    'id' => 3,
                    'name' => 'Timis',
                    'code' => 'TM'
                ],
                'samedayId' => 1003
            ],
            [
                'id' => 4,
                'name' => 'Brasov',
                'county' => [
                    'id' => 4,
                    'name' => 'Brasov',
                    'code' => 'BV'
                ],
                'samedayId' => 1004
            ],
            [
                'id' => 5,
                'name' => 'Iasi',
                'county' => [
                    'id' => 5,
                    'name' => 'Iasi',
                    'code' => 'IS'
                ],
                'samedayId' => 1005
            ]
        ];
        
        // Filter by county if specified
        if ($county !== null) {
            $filteredCities = array_filter($allCities, function($city) use ($county) {
                return strcasecmp($city['county']['name'], $county) === 0 || 
                       strcasecmp($city['county']['code'], $county) === 0;
            });
            
            $cities = array_values($filteredCities);
        } else {
            $cities = $allCities;
        }
        
        return [
            'success' => true,
            'data' => $cities
        ];
    }
    
    public function getCounties()
    {
        // Mock counties data
        return [
            'success' => true,
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Bucharest',
                    'code' => 'B'
                ],
                [
                    'id' => 2,
                    'name' => 'Cluj',
                    'code' => 'CJ'
                ],
                [
                    'id' => 3,
                    'name' => 'Timis',
                    'code' => 'TM'
                ],
                [
                    'id' => 4,
                    'name' => 'Brasov',
                    'code' => 'BV'
                ],
                [
                    'id' => 5,
                    'name' => 'Iasi',
                    'code' => 'IS'
                ]
            ]
        ];
    }
    
    public function calculatePrice($request)
    {
        // Mock tariff calculation
        return [
            'success' => true,
            'data' => [
                'fanService' => [
                    'id' => 1,
                    'price' => 22.58,
                    'currency' => 'RON',
                    'service' => 'Fan Courier'
                ],
                'samedayService' => [
                    'id' => 1,
                    'price' => 23.72,
                    'currency' => 'RON',
                    'service' => 'Sameday 24H'
                ]
            ]
        ];
    }
    
    public function authenticate()
    {
        // Mock successful authentication
        return [
            'success' => true,
            'token' => 'mock_token_' . time(),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 day'))
        ];
    }
    
    public function createAwb($request)
    {
        // Mock AWB creation
        return [
            'success' => true,
            'data' => [
                'awb' => 'AWB' . rand(1000000, 9999999),
                'created_at' => date('Y-m-d H:i:s'),
                'pdf_link' => '/api/mock-awb-pdf/' . rand(10000, 99999),
                'status' => 'created'
            ]
        ];
    }
    
    
}