<?php

namespace App\Services\Hotel;

use App\Common\SingletonTrait;
use App\Entities\HotelEntity;
use App\Entities\RoomEntity;
use App\Services\Room\RoomService;
use App\Common\Timers;
use App\Common\PDOSingleton;
use PDO;

class OneRequestHotelService extends AbstractHotelService {

    use SingletonTrait;

    protected function __construct () {
        parent::__construct( new RoomService() );
    }

    protected function getDB () : PDO {
        $timer = Timers::getInstance();
        $timerId = $timer->startTimer('getBD');
        $PDO = PDOSingleton::get();
        $timer->endTimer('getBD', $timerId);
        return $PDO;
    }

    public function list ( array $args = [] ) : array {

        $db = $this->getDB();

        $query = "SELECT
        user.ID AS id,
        user.display_name AS name,
        address_1Data.meta_value as hotel_address_1,
        address_2Data.meta_value as hotel_address_2,
        address_cityData.meta_value as hotel_address_city,
        address_zipData.meta_value as hotel_address_zip,
        address_countryData.meta_value as hotel_address_country,
        geo_latData.meta_value as geo_lat,
        geo_lngData.meta_value as geo_lng,
        phoneData.meta_value as phone,
        coverImageData.meta_value as coverImage,
        postData.ID as cheapestRoomid,
        postData.price as price,
        postData.surface as surface,
        postData.bedroom as bedRoomsCount,
        postData.bathroom as bathRoomsCount,
        postData.type as type,
        COUNT(reviewData.meta_value) as ratingCount,
        AVG(reviewData.meta_value) as rating ";

        if(!empty($args["distance"])){
            $query .= ",
          111.111
          * DEGREES(ACOS(LEAST(1.0, COS(RADIANS( geo_latData.meta_value ))
          * COS(RADIANS( :lat ))
          * COS(RADIANS( geo_lngData.meta_value - :lng ))
          + SIN(RADIANS( geo_latData.meta_value ))
          * SIN(RADIANS( :lat ))))) AS distanceKM";
        }

        $query .= "
        FROM
        wp_users AS USER
        INNER JOIN wp_usermeta as address_1Data ON address_1Data.user_id = USER.ID AND address_1Data.meta_key = 'address_1'
        INNER JOIN wp_usermeta as address_2Data ON address_2Data.user_id = USER.ID AND address_2Data.meta_key = 'address_2'
        INNER JOIN wp_usermeta as address_cityData ON address_cityData.user_id = USER.ID AND address_cityData.meta_key = 'address_city'
        INNER JOIN wp_usermeta as address_zipData ON address_zipData.user_id = USER.ID AND address_zipData.meta_key = 'address_zip'
        INNER JOIN wp_usermeta as address_countryData ON address_countryData.user_id = USER.ID AND address_countryData.meta_key = 'address_country'
        INNER JOIN wp_usermeta as geo_latData ON geo_latData.user_id = USER.ID AND geo_latData.meta_key = 'geo_lat'
        INNER JOIN wp_usermeta as geo_lngData ON geo_lngData.user_id = USER.ID AND geo_lngData.meta_key = 'geo_lng'
        INNER JOIN wp_usermeta as coverImageData ON coverImageData.user_id = USER.ID AND coverImageData.meta_key = 'coverImage'
        INNER JOIN wp_usermeta as phoneData ON phoneData.user_id = USER.ID AND phoneData.meta_key  = 'phone'
        INNER JOIN wp_posts    as rating_postData ON rating_postData.post_author = USER.ID AND rating_postData.post_type = 'review'
        INNER JOIN wp_postmeta as reviewData ON reviewData.post_id = rating_postData.ID AND reviewData.meta_key = 'rating'
    
        INNER JOIN (SELECT
            post.ID,
            post.post_author,
            MIN(CAST(priceData.meta_value AS UNSIGNED)) AS price,
            CAST(surfaceData.meta_value  AS UNSIGNED) AS surface,
            CAST(roomsData.meta_value AS UNSIGNED) AS bedroom,
            CAST(bathRoomsData.meta_value AS UNSIGNED) AS bathroom,
            typeData.meta_value AS type
            FROM tp.wp_posts AS post
            INNER JOIN tp.wp_postmeta AS priceData ON post.ID = priceData.post_id AND priceData.meta_key = 'price'
            INNER JOIN wp_postmeta as surfaceData ON surfaceData.post_id = post.ID AND surfaceData.meta_key = 'surface'
            INNER JOIN wp_postmeta as roomsData ON roomsData.post_id = post.ID AND roomsData.meta_key = 'bedrooms_count'
            INNER JOIN wp_postmeta as bathRoomsData ON bathRoomsData.post_id = post.ID AND bathRoomsData.meta_key = 'bathrooms_count'
            INNER JOIN wp_postmeta as typeData ON typeData.post_id = post.ID AND typeData.meta_key = 'type'
            WHERE post.post_type = 'room'
            GROUP BY post.ID
        ) AS postData ON user.ID = postData.post_author";

        $whereClauses = [];

        if (isset ($args['surface']['min'])){
            $whereClauses[] = " surface >= ". $args['surface']['min'];
        }
        if (isset ($args['surface']['max'])){
            $whereClauses[] =  " surface <= ". $args['surface']['max'];
        }
        if (isset ($args['price']['min'])){
            $whereClauses[] = " price >= ". $args['price']['min'];
        }
        if (isset ($args['price']['max'])){
            $whereClauses[] =  " price <= ". $args['price']['max'];
        }
        if (isset ($args['rooms'])){
            $whereClauses[] =  " bedroom >= ". $args['rooms'];
        }
        if (isset ($args['bathRooms'])){
            $whereClauses[] =  " bathroom >= ". $args['bathRooms'] ;
        }
        if (isset ($args['types']) && count($args['types']) > 0){
            $whereClauses[] =  ' type IN ("'.implode('","',$args['types']).'")';
        }

        if (!empty($whereClauses)) {
            $arg = 0;
            $query .= " WHERE ";
            foreach($whereClauses as $clause) {
                if ($arg != 0) {
                    $query .= " AND ";
                } else {
                    $arg += 1;
                }
                $query .= $clause;
            }
        }

        $query .= " GROUP BY user.ID";

        if(!empty($args["distance"])){
            $query .= " HAVING distanceKM <= :distance";
        }

        $query .=" ORDER BY `cheapestRoomId` ASC";

        $stmt = $db->prepare($query);

        if(empty($args["distance"])){
            $stmt->execute();
        }
        else{
            $stmt->execute([ 'lat' => $args["lat"],'lng' => $args["lng"],'distance' => $args["distance"]]);
        }

        $results = $stmt->fetchAll( PDO::FETCH_ASSOC );

        $hotels = [];

        foreach($results as $result){
            $address = [
                'address_1' => $result['hotel_address_1'],
                'address_2' => $result['hotel_address_2'],
                'address_city' => $result['hotel_address_city'],
                'address_zip' => $result['hotel_address_zip'],
                'address_country' => $result['hotel_address_country']
            ];
            $cheapestRoom = (new RoomEntity())
                ->setId( $result['cheapestRoomid'] )
                ->setTitle( '' )
                ->setSurface( $result['surface'] )
                ->setPrice( $result['price'] )
                ->setBedRoomsCount( $result['bedRoomsCount'] )
                ->setBathRoomsCount( $result['bathRoomsCount'] )
                ->setType( $result['type'] );
            $hotel = ( new HotelEntity() )
                ->setId( $result['id'] )
                ->setName( $result['name'] );

            $hotel->setAddress( $address );
            $hotel->setGeoLat( $result['geo_lat'] );
            $hotel->setGeoLng( $result['geo_lng'] );
            $hotel->setImageUrl( $result['coverImage'] );
            $hotel->setPhone( $result['phone'] );
            $hotel->setRating( intval($result['rating']) );
            $hotel->setRatingCount( $result['ratingCount'] );
            $hotel->setCheapestRoom( $cheapestRoom );

            $hotels[] = $hotel;
        }

        return $hotels;
    }

}