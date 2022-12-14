<?php

namespace App\Services\Hotel;

use App\Common\FilterException;
use App\Common\PDOSingleton;
use App\Common\SingletonTrait;
use App\Common\Timers;
use App\Entities\HotelEntity;
use App\Entities\RoomEntity;
use App\Services\Room\RoomService;
use Exception;
use PDO;


/**
 * Une classe utilitaire pour récupérer les données des magasins stockés en base de données
 */
class UnoptimizedHotelService extends AbstractHotelService {
  
  use SingletonTrait;
  
  
  protected function __construct () {
    parent::__construct( new RoomService() );
  }
  
  
  /**
   * Récupère une nouvelle instance de connexion à la base de donnée
   *
   * @return PDO
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getDB () : PDO {
    $timer = Timers::getInstance();
    $timerId = $timer->startTimer('getDB');
    $pdo = PDOSingleton::get();
    $timer->endTimer('getDB', $timerId);
    return $pdo;
  }
  
  
  /**
   * Récupère une méta-donnée de l'instance donnée
   *
   * @param int    $userId
   *
   * @return array|null
   */
  protected function getMeta ( int $userId ) : ?array {
    $timer = Timers::getInstance();
    $timerId = $timer->startTimer('getMeta');

    $db = $this->getDB();
    $stmt = $db->prepare( "SELECT meta_key,meta_value FROM wp_usermeta WHERE user_id = :userid");
    $stmt->bindParam('userid', $userId, PDO::PARAM_INT);

    $stmt->execute();

    $results = $stmt->fetchAll( PDO::FETCH_ASSOC );
    $output = [];

    foreach($results as $result){
      $output[$result['meta_key']] = $result['meta_value'];
    }

    $timer->endTimer('getMeta', $timerId);

    return $output;
  }
  
  
  /**
   * Récupère toutes les meta données de l'instance donnée
   *
   * @param HotelEntity $hotel
   *
   * @return array
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getMetas ( HotelEntity $hotel ) : array {

    $data = $this->getMeta($hotel->getId());

    $metaDatas = [
      'address' => [
        'address_1' => $data['address_1'],
        'address_2' => $data['address_2'],
        'address_city' => $data['address_city'],
        'address_zip' => $data['address_zip'],
        'address_country' => $data['address_country'],
      ],
      'geo_lat' => $data['geo_lat'],
      'geo_lng' =>  $data['geo_lng'],
      'coverImage' =>  $data['coverImage'],
      'phone' =>  $data['phone'],
    ];

    return $metaDatas;
  }
  
  
  /**
   * Récupère les données liées aux évaluations des hotels (nombre d'avis et moyenne des avis)
   *
   * @param HotelEntity $hotel
   *
   * @return array{rating: int, count: int}
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getReviews ( HotelEntity $hotel ) : array {
    $timer = Timers::getInstance();
    $timerId = $timer->startTimer('getReviews');
    // Récupère tous les avis d'un hotel

    $stmt = $this->getDB()->prepare( "SELECT ROUND(AVG(meta_value)) AS rating, COUNT(meta_value) AS count FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review'" );
    $stmt->execute( [ 'hotelId' => $hotel->getId() ] );
    $reviews = $stmt->fetch( PDO::FETCH_ASSOC );

    $timer->endTimer('getReviews', $timerId);
    return $reviews;
  }
  
  
  /**
   * Récupère les données liées à la chambre la moins chère des hotels
   *
   * @param HotelEntity $hotel
   * @param array{
   *   search: string | null,
   *   lat: string | null,
   *   lng: string | null,
   *   price: array{min:float | null, max: float | null},
   *   surface: array{min:int | null, max: int | null},
   *   rooms: int | null,
   *   bathRooms: int | null,
   *   types: string[]
   * }                  $args Une liste de paramètres pour filtrer les résultats
   *
   * @throws FilterException
   * @return RoomEntity
   */
  protected function getCheapestRoom ( HotelEntity $hotel, array $args = [] ) : RoomEntity {
    $timer = Timers::getInstance();
    $timerId = $timer->startTimer('getCheapestRoom');
    // On charge toutes les chambres de l'hôtel

    /**
     * On convertit les lignes en instances de chambres (au passage ça charge toutes les données).
     *
     * @var RoomEntity[] $rooms ;
     */

      $query = "SELECT post.ID,
        post.post_title AS title,
        priceData.meta_value AS price, 
        surfaceData.meta_value AS surface, 
        typeData.meta_value AS type, 
        bedroomsCountData.meta_value AS bedrooms, 
        bathroomsCountData.meta_value AS bathrooms
        FROM wp_posts AS post
        INNER JOIN wp_postmeta AS priceData ON post.ID = PriceData.post_id AND PriceData.meta_key = 'price'
        INNER JOIN wp_postmeta AS surfaceData ON post.ID = SurfaceData.post_id AND SurfaceData.meta_key = 'surface'
        INNER JOIN wp_postmeta AS typeData ON post.ID = TypeData.post_id AND TypeData.meta_key = 'type'
        INNER JOIN wp_postmeta AS bedroomsCountData ON post.ID = BedroomsCountData.post_id AND BedroomsCountData.meta_key = 'bedrooms_count'
        INNER JOIN wp_postmeta AS bathroomsCountData ON post.ID = BathroomsCountData.post_id AND BathroomsCountData.meta_key = 'bathrooms_count'";

      $whereClauses = [];

      if (isset ($args['surface']['min'])){
          $whereClauses[] = "surfaceData.meta_value >= :surfaceMin";
      }
      if (isset ($args['surface']['max'])){
          $whereClauses[] =  "surfaceData.meta_value <= :surfaceMax";
      }
      if (isset ($args['price']['min'])){
          $whereClauses[] = "priceData.meta_value >= :priceMin";
      }
      if (isset ($args['price']['max'])){
          $whereClauses[] =  "priceData.meta_value <= :priceMax";
      }
      if (isset ($args['rooms'])){
          $whereClauses[] =  "bedroomsCountData.meta_value >= :rooms";
      }
      if (isset ($args['bathRooms'])){
          $whereClauses[] =  "bathroomsCountData.meta_value >= :bathRooms";
      }
      if (isset ($args['types']) && count($args['types']) > 0){
          $whereClauses[] =  'typeData.meta_value IN ("'.implode('","',$args['types']).'")';
      }

      $query .=" WHERE post_author = :hotelId AND post_type = 'room'";

      if ( count($whereClauses) > 0 ) {
          $query .= " AND " . implode(' AND ', $whereClauses);
      }

      $stmt = $this->getDB()->prepare( $query );

      if ( isset( $args['surface']['min'] ) ) {
          $stmt->bindParam('surfaceMin', $args['surface']['min'], PDO::PARAM_INT);
      }

      if ( isset( $args['surface']['max'] ) ) {
          $stmt->bindParam('surfaceMax', $args['surface']['max'], PDO::PARAM_INT);
      }

      if ( isset( $args['price']['min'] ) ) {
          $stmt->bindParam('priceMin', $args['price']['min'], PDO::PARAM_INT);
      }

      if ( isset( $args['price']['max'] ) ) {
          $stmt->bindParam('priceMax', $args['price']['max'], PDO::PARAM_INT);
      }

      if (isset ($args['rooms'])){
          $stmt->bindParam('rooms', $args['rooms'], PDO::PARAM_INT);
      }

      if (isset ($args['bathRooms'])){
          $stmt->bindParam('bathRooms', $args['bathRooms'], PDO::PARAM_INT);
      }

      $stmt->bindValue('hotelId', $hotel->getId(), PDO::PARAM_INT);
      $stmt->execute();

      $room = $stmt->fetch();

      if ( !$room )
          throw new FilterException( "Aucune chambre ne correspond aux critères" );

      $cheapestRoom = (new RoomEntity())
          ->setId($room['ID'])
          ->setTitle($room ['title'])
          ->setSurface($room ['surface'])
          ->setPrice($room ['price'])
          ->setBedRoomsCount($room ['bedrooms'])
          ->setBathRoomsCount($room ['bathrooms'])
          ->setType($room ['type']);

      $timer->endTimer('getCheapestRoom', $timerId);
      return $cheapestRoom;
  }
  
  
  /**
   * Calcule la distance entre deux coordonnées GPS
   *
   * @param $latitudeFrom
   * @param $longitudeFrom
   * @param $latitudeTo
   * @param $longitudeTo
   *
   * @return float|int
   */
  protected function computeDistance ( $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo ) : float|int {
    return ( 111.111 * rad2deg( acos( min( 1.0, cos( deg2rad( $latitudeTo ) )
          * cos( deg2rad( $latitudeFrom ) )
          * cos( deg2rad( $longitudeTo - $longitudeFrom ) )
          + sin( deg2rad( $latitudeTo ) )
          * sin( deg2rad( $latitudeFrom ) ) ) ) ) );
  }
  
  
  /**
   * Construit une ShopEntity depuis un tableau associatif de données
   *
   * @throws Exception
   */
  protected function convertEntityFromArray ( array $data, array $args ) : HotelEntity {
    $hotel = ( new HotelEntity() )
      ->setId( $data['ID'] )
      ->setName( $data['display_name'] );
    
    // Charge les données meta de l'hôtel
    $metasData = $this->getMetas( $hotel );
    $hotel->setAddress( $metasData['address'] );
    $hotel->setGeoLat( $metasData['geo_lat'] );
    $hotel->setGeoLng( $metasData['geo_lng'] );
    $hotel->setImageUrl( $metasData['coverImage'] );
    $hotel->setPhone( $metasData['phone'] );
    
    // Définit la note moyenne et le nombre d'avis de l'hôtel
    $reviewsData = $this->getReviews( $hotel );
    $hotel->setRating( $reviewsData['rating'] );
    $hotel->setRatingCount( $reviewsData['count'] );
    
    // Charge la chambre la moins chère de l'hôtel
    $cheapestRoom = $this->getCheapestRoom( $hotel, $args );
    $hotel->setCheapestRoom($cheapestRoom);
    
    // Verification de la distance
    if ( isset( $args['lat'] ) && isset( $args['lng'] ) && isset( $args['distance'] ) ) {
      $hotel->setDistance( $this->computeDistance(
        floatval( $args['lat'] ),
        floatval( $args['lng'] ),
        floatval( $hotel->getGeoLat() ),
        floatval( $hotel->getGeoLng() )
      ) );
      
      if ( $hotel->getDistance() > $args['distance'] )
        throw new FilterException( "L'hôtel est en dehors du rayon de recherche" );
    }

    return $hotel;
  }
  
  
  /**
   * Retourne une liste de boutiques qui peuvent être filtrées en fonction des paramètres donnés à $args
   *
   * @param array{
   *   search: string | null,
   *   lat: string | null,
   *   lng: string | null,
   *   price: array{min:float | null, max: float | null},
   *   surface: array{min:int | null, max: int | null},
   *   bedrooms: int | null,
   *   bathrooms: int | null,
   *   types: string[]
   * } $args Une liste de paramètres pour filtrer les résultats
   *
   * @throws Exception
   * @return HotelEntity[] La liste des boutiques qui correspondent aux paramètres donnés à args
   */
  public function list ( array $args = [] ) : array {
    $db = $this->getDB();
    $stmt = $db->prepare( "SELECT * FROM wp_users" );
    $stmt->execute();
    
    $results = [];
    foreach ( $stmt->fetchAll( PDO::FETCH_ASSOC ) as $row ) {
      try {
        $results[] = $this->convertEntityFromArray( $row, $args );
      } catch ( FilterException ) {
        // Des FilterException peuvent être déclenchées pour exclure certains hotels des résultats
      }
    }
    
    
    return $results;
  }
}