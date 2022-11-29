<?php

namespace App\Controllers\Hotel;

use App\Controllers\AbstractController;
use App\Common\Timers;
use App\Services\Hotel\AbstractHotelService;
use App\Services\Room\AbstractRoomService;
use function App\Common\get_template;
use function App\Common\sanitize;
use const App\__PROJECT_ROOT__;

/**
 * Contrôleur en charge de l'affichage de la liste des hotels
 */
class HotelListController extends AbstractController {
  
  /**
   * Le service qui sera utilisé pour récupérer les hôtels depuis la DB
   * @var AbstractHotelService
   */
  private readonly AbstractHotelService $hotelService;
  
  /**
   * Le service qui sera utilisé pour récupérer les chambres depuis la DB
   * @var AbstractRoomService
   */
  private readonly AbstractRoomService $roomService;
  
  /**
   * Défini les types qui sont valides dans les filtres pour empêcher qu'on tente d'insérer des filtres non-valides dans l'URL
   */
  private const ALLOWED_TYPES = [
    'Maison',
    'Appartement',
    'Chambre',
  ];
  
  
  /**
   * @param AbstractHotelService $hotelService
   */
  public function __construct ( AbstractHotelService $hotelService ) {
    $this->hotelService = $hotelService;
    $this->roomService = $hotelService->getRoomService();
  }
  
  
  /**
   * Extrait les valeurs saisies dans le formulaire
   *
   * @return array{
   *   search: string,
   *   lat: float|null,
   *   lng: float|null,
   *   types: string,
   *   price: array{min: int|null, max: int|null},
   *   surface: array{min: int|null, max: int|null},
   *   rooms: int|null,
   *   bathRooms: int|null,
   *   counters: int[],
   *   distance: int|null
   * }
   */
  private function getFormValues () : array {
    $types = [];
    
    foreach ( self::ALLOWED_TYPES as $type ) {
      $types[$type] = in_array( $type, $_GET['types'] ?? [] );
    }
    
    $price = [
      'min' => intval( $_GET['price']['min'] ?? null ),
      'max' => intval( $_GET['price']['max'] ?? null ),
    ];
    
    $surface = [
      'min' => intval( $_GET['surface']['min'] ?? null ),
      'max' => intval( $_GET['surface']['max'] ?? null ),
    ];
    
    $rooms = intval( $_GET['rooms'] ?? null );
    $bathRooms = intval( $_GET['bathRooms'] ?? null );
    
    return array(
      'types' => $types,
      'search' => sanitize( $_GET['search'] ?? null ),
      'lat' => isset( $_GET['lat'] ) && !empty($_GET['lat'])
        ? floatval( $_GET['lat'] )
        : null,
      'lng' => isset( $_GET['lng'] ) && !empty($_GET['lng'])
        ? floatval( $_GET['lng'] )
        : null,
      'distance' => isset( $_GET['distance'] ) && intval($_GET['distance']) > 0
        ? intval( $_GET['distance'] )
        : null,
      'price' => [
        'min' => $price['min'] > 0
          ? $price['min']
          : null,
        'max' => $price['max'] > 0
          ? $price['max']
          : null,
      ],
      'surface' => [
        'min' => $surface['min'] > 0
          ? $surface['min']
          : null,
        'max' => $surface['max'] > 0
          ? $surface['max']
          : null,
      ],
      'rooms' => $rooms > 0
        ? $rooms
        : null,
      'bathRooms' => $bathRooms > 0
        ? $bathRooms
        : null,
      'counters' => $this->roomService->getCountByType(),
    );
  }
  
  
  /**
   * Récupère les valeurs et les donne au template pour générer le HTML
   * @return void
   */
  public function render () : void {
    $formValues = $this->getFormValues();
    
    $typesCounters = $this->roomService->getCountByType();
    $typesCountersLabels = [];
    foreach ( $typesCounters as $type => $count ) {
      $typesCountersLabels[$type] = "$type ($count)";
    }
    
    $args = $formValues;
    $args['types'] = [];
    foreach ( $formValues['types'] as $type => $checked ) {
      if ( ! $checked )
        continue;
      $args['types'][] = $type;
    }
    
    $hotels = $this->hotelService->list( $args );

    header('Server-Timing: ' . Timers::getInstance()->getTimers() );
    echo get_template( __PROJECT_ROOT__ . "/Views/hotel-list.php", [
      'hotels' => $hotels,
      'filters' => $formValues,
      'typesCounters' => $typesCountersLabels,
    ] );
  }
}