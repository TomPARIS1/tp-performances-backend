<?php

namespace App\Services\Room;

use App\Common\PDOSingleton;
use App\Entities\RoomEntity;
use PDO;

class RoomService extends AbstractRoomService {
  
  private PDO $db;
  
  public function __construct() {
    $this->db = PDOSingleton::get();
  }
  
  protected function getDB() : PDO {
    return $this->db;
  }
  
  public function get(int $id) : RoomEntity {
    $stmt = $this->getDB()->prepare("SELECT ID, post_title FROM wp_posts WHERE ID = :roomId AND post_type = 'room'");
    $stmt->execute(['roomId' => $id]);
    
    $data = $stmt->fetch(PDO::FETCH_ASSOC );
    $room = (new RoomEntity())
      ->setId( $data['ID'] )
      ->setTitle( $data['post_title'] );
    
    $this->loadMetas( $room );
    
    return $room;
  }
  
  protected function getMetas ( int $roomId ) : array {
    $stmt = $this->getDB()->prepare( "SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = :roomId" );
    $stmt->execute( [ 'roomId' => $roomId ] );
    
    $results = [];
    foreach ( $stmt->fetchAll( PDO::FETCH_ASSOC ) as $row ) {
      $results[$row['meta_key']] = $row['meta_value'];
    }
    
    return $results;
  }
  
  
  /**
   * Charge toutes les meta données de l'instance donnée
   *
   * @param RoomEntity $room
   *
   * @return void
   */
  protected function loadMetas ( RoomEntity $room ) : void {
    $metas = $this->getMetas( $room->getId() );
    
    $room->setBathRoomsCount( $metas['bathrooms_count'] );
    $room->setBedRoomsCount( $metas['bedrooms_count'] );
    $room->setCoverImageUrl( $metas['coverImage'] );
    $room->setSurface( $metas['surface'] );
    $room->setType( $metas['type'] );
    $room->setPrice( $metas['price'] );
  }
  
  
  /**
   * @inheritDoc
   */
  public function getCountByType() : array {
    $stmt = $this->getDB()->prepare("
SELECT
	meta.meta_value as roomType,
	COUNT(post.ID) as count
FROM
	tp.wp_posts as post
	INNER JOIN tp.wp_postmeta as meta ON post.ID = meta.post_id AND meta.meta_key = 'type'
WHERE
	post_type = 'room'
GROUP BY
	roomType;
");
    $stmt->execute();
    
    $output = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $output[$row['roomType']] = $row['count'];
    }
    
    return $output;
  }
}