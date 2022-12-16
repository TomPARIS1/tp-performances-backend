Vous pouvez utiliser ce [GSheets](https://docs.google.com/spreadsheets/d/13Hw27U3CsoWGKJ-qDAunW9Kcmqe9ng8FROmZaLROU5c/copy?usp=sharing) pour suivre l'évolution de l'amélioration de vos performances au cours du TP 

## Question 2 : Utilisation Server Timing API

**Temps de chargement initial de la page** : 31.3s

**Choix des méthodes à analyser** :

- `getCheapestRoom` 16.39s
- `getMeta` 4.12s
- `getReviews` 9.01s



## Question 3 : Réduction du nombre de connexions PDO

**Temps de chargement de la page** : 29.6s

**Temps consommé par `getDB()`** 

- **Avant** 1.15s

- **Après** 1.64ms


## Question 4 : Délégation des opérations de filtrage à la base de données

**Temps de chargement globaux** 

- **Avant** 29.6s

- **Après** 22.7


#### Amélioration de la méthode `getMeta` et donc de la méthode `getMetas` :

- **Avant** 4.12s

```sql
SELECT * FROM wp_usermeta
```

- **Après** 171.96ms

```sql
SELECT meta_key,meta_value FROM wp_usermeta WHERE user_id = :userid
```



#### Amélioration de la méthode `getCheapestRoom` :

- **Avant** 16.39s

```sql
SELECT * FROM wp_posts WHERE post_author = :hotelId AND post_type = 'room'
```

- **Après** 9.24s

```sql
	SELECT post.ID,
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
        INNER JOIN wp_postmeta AS bathroomsCountData ON post.ID = BathroomsCountData.post_id AND BathroomsCountData.meta_key = 'bathrooms_count'
```



#### Amélioration de la méthode `getReviews` :

- **Avant** 9.01s

```sql
SELECT * FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review'
```

- **Après** 6.32s

```sql
SELECT ROUND(AVG(meta_value)) AS rating, COUNT(meta_value) AS count FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review'
```



## Question 5 : Réduction du nombre de requêtes SQL pour `getDB`

|                              | **Avant** | **Après** |
|------------------------------|-----------|-----------|
| Nombre d'appels de `getDB()` | 2201      | 601       |
| Temps de `getMeta`           | 4.12s     | 171.96ms  |

## Question 6 : Création d'un service basé sur une seule requête SQL

|                              | **Avant** | **Après** |
|------------------------------|-----------|-----------|
| Nombre d'appels de `getDB()` | 601       | 1         |
| Temps de chargement global   | 22.7s     | 4.34s     |

**Requête SQL**

```SQL
	SELECT
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
		AVG(reviewData.meta_value) as rating 
        FROM  wp_users AS USER
		INNER JOIN wp_usermeta as address_1Data ON address_1Data.user_id = USER.ID AND address_1Data.meta_key = 'address_1'
		INNER JOIN wp_usermeta as address_2Data ON address_2Data.user_id = USER.ID AND address_2Data.meta_key = 'address_2'
		INNER JOIN wp_usermeta as address_cityData ON address_cityData.user_id = USER.ID AND address_cityData.meta_key = 'address_city'
		INNER JOIN wp_usermeta as address_zipData ON address_zipData.user_id = USER.ID AND address_zipData.meta_key = 'address_zip'
		INNER JOIN wp_usermeta as address_countryData ON address_countryData.user_id = USER.ID AND address_countryData.meta_key = 'address_country'
		INNER JOIN wp_usermeta as geo_latData ON geo_latData.user_id = USER.ID AND geo_latData.meta_key = 'geo_lat'
		INNER JOIN wp_usermeta as geo_lngData ON geo_lngData.user_id = USER.ID AND geo_lngData.meta_key = 'geo_lng'
		INNER JOIN wp_usermeta as coverImageData ON coverImageData.user_id = USER.ID AND coverImageData.meta_key = 'coverImage'
		INNER JOIN wp_usermeta as phoneData ON phoneData.user_id = USER.ID AND phoneData.meta_key = 'phone'
		INNER JOIN wp_posts as rating_postData ON rating_postData.post_author = USER.ID AND rating_postData.post_type = 'review'
		INNER JOIN wp_postmeta as reviewData ON reviewData.post_id = rating_postData.ID AND reviewData.meta_key = 'rating'
        INNER JOIN (SELECT
			post.ID,
			post.post_author,
			MIN(CAST(priceData.meta_value AS UNSIGNED)) AS price,
			CAST(surfaceData.meta_value  AS UNSIGNED) AS surface,
			CAST(roomsData.meta_value AS UNSIGNED) AS bedroom,
			CAST(bathRoomsData.meta_value AS UNSIGNED) AS bathroom,
			typeData.meta_value AS type
			FROM
			tp.wp_posts AS post
			INNER JOIN tp.wp_postmeta AS priceData ON post.ID = priceData.post_id AND priceData.meta_key = 'price'
			INNER JOIN wp_postmeta as surfaceData ON surfaceData.post_id = post.ID AND surfaceData.meta_key = 'surface'
			INNER JOIN wp_postmeta as roomsData ON roomsData.post_id = post.ID AND roomsData.meta_key = 'bedrooms_count'
			INNER JOIN wp_postmeta as bathRoomsData ON bathRoomsData.post_id = post.ID AND bathRoomsData.meta_key = 'bathrooms_count'
			INNER JOIN wp_postmeta as typeData ON typeData.post_id = post.ID AND typeData.meta_key = 'type'
			WHERE post.post_type = 'room'
			GROUP BY post.ID
		) AS postData ON user.ID = postData.post_author
        GROUP BY user.ID
        ORDER BY `cheapestRoomId` ASC
```

## Question 7 : ajout d'indexes SQL

**Indexes ajoutés**

- `wp_posts` : `post_author`
- `wp_postmeta` : `post_id`
- `wp_usermeta` : `user_id`

**Requête SQL d'ajout des indexes** 

```sql
ALTER TABLE `wp_posts` ADD INDEX(`post_author`);
ALTER TABLE `wp_postmeta` ADD INDEX(`post_id`);
ALTER TABLE `wp_usermeta` ADD INDEX(`user_id`);
```

| Temps de chargement de la page | Sans filtre | Avec filtres |
|--------------------------------|-------------|--------------|
| `UnoptimizedService`           | 19.6s       | 1.32s        |
| `OneRequestService`            | 4.22s       | 1.26s        |
[Filtres à utiliser pour mesurer le temps de chargement](http://localhost/?types%5B%5D=Maison&types%5B%5D=Appartement&price%5Bmin%5D=200&price%5Bmax%5D=230&surface%5Bmin%5D=130&surface%5Bmax%5D=150&rooms=5&bathRooms=5&lat=46.988708&lng=3.160778&search=Nevers&distance=30)




## Question 8 : restructuration des tables

**Temps de chargement de la page**

| Temps de chargement de la page | Sans filtre | Avec filtres |
|--------------------------------|-------------|--------------|
| `OneRequestService`            | TEMPS       | TEMPS        |
| `ReworkedHotelService`         | TEMPS       | TEMPS        |

[Filtres à utiliser pour mesurer le temps de chargement](http://localhost/?types%5B%5D=Maison&types%5B%5D=Appartement&price%5Bmin%5D=200&price%5Bmax%5D=230&surface%5Bmin%5D=130&surface%5Bmax%5D=150&rooms=5&bathRooms=5&lat=46.988708&lng=3.160778&search=Nevers&distance=30)

### Table `hotels` (200 lignes)

```SQL
CREATE TABLE `hotels` (
	`id` bigint(255) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`name` varchar(255) NOT NULL,
	`email` varchar(255) NOT NULL,
	`address_1` varchar(255) NOT NULL,
	`address_2` varchar(255) NOT NULL,
	`address_city` varchar(255) NOT NULL,
	`address_zipcode` varchar(255) NOT NULL,
	`address_country` varchar(100) NOT NULL,
	`geo_lat` float NOT NULL,
	`geo_lng` float NOT NULL,
	`phone` varchar(255) NOT NULL,
	`coverImage` longtext NOT NULL
);
```

```SQL
INSERT INTO hotels (
 SELECT
	user.ID as id,
	user.display_name as name,
	user.user_email as email,
	address_1_meta.meta_value as address_1,
	address_2_meta.meta_value as address_2,
	address_city_meta.meta_value as address_city,
	address_zip_meta.meta_value as address_zip,
	address_country_meta.meta_value as hotel_address_country,
	geo_lat_meta.meta_value as geo_lat,
	geo_lng_meta.meta_value as geo_lng,
	phone_meta.meta_value as phone,
	coverImage_meta.meta_value as coverImage
 FROM wp_users as USER
	INNER JOIN wp_usermeta as address_1_meta ON address_1_meta.user_id = user.ID AND address_1_meta.meta_key = 'address_1'
	INNER JOIN wp_usermeta as address_2_meta ON address_2_meta.user_id = user.ID AND address_2_meta.meta_key = 'address_2'
	INNER JOIN wp_usermeta as address_city_meta ON address_city_meta.user_id = user.ID AND address_city_meta.meta_key = 'address_city'
	INNER JOIN wp_usermeta as address_zip_meta ON address_zip_meta.user_id = user.ID AND address_zip_meta.meta_key = 'address_zip'
	INNER JOIN wp_usermeta as address_country_meta ON address_country_meta.user_id = user.ID AND address_country_meta.meta_key = 'address_country'
	INNER JOIN wp_usermeta as geo_lat_meta ON geo_lat_meta.user_id = user.ID AND geo_lat_meta.meta_key = 'geo_lat'
	INNER JOIN wp_usermeta as geo_lng_meta ON geo_lng_meta.user_id = user.ID AND geo_lng_meta.meta_key = 'geo_lng'
	INNER JOIN wp_usermeta as coverImage_meta ON coverImage_meta.user_id = user.ID AND coverImage_meta.meta_key = 'coverImage'
	INNER JOIN wp_usermeta as phone_meta ON phone_meta.user_id = user.ID AND phone_meta.meta_key = 'phone'
 GROUP BY USER.ID
);
```

### Table `rooms` (1 200 lignes)

```SQL
CREATE TABLE `rooms` (
	`id` bigint(255) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`id_hotel` bigint(255) UNSIGNED NOT NULL,
	`title` varchar(255) NOT NULL,
	`price` float NOT NULL,
	`image` varchar(400) NOT NULL,
	`bedrooms` int UNSIGNED NOT NULL,
	`bathrooms` int UNSIGNED NOT NULL,
	`surface` FLOAT UNSIGNED NOT NULL,
	`type` varchar(255) NOT NULL,
	FOREIGN KEY (`id_hotel`) REFERENCES `hotels`(`id`)
);
```

```SQL
INSERT INTO rooms(
 SELECT
	post.ID as id,
	post.post_author as hotel_id,
	post.post_title as title,
	priceData.meta_value as price,
	img_meta.meta_value as image,
	roomsData.meta_value as bedrooms,
	bathroomsData.meta_value as bathrooms,
	surfaceData.meta_value as surface,
	typeData.meta_value as type
 FROM wp_posts as POST
	INNER JOIN tp.wp_postmeta as priceData ON post.ID = priceData.post_id AND priceData.meta_key = 'price'
	INNER JOIN wp_postmeta as surfaceData ON surfaceData.post_id = post.ID AND surfaceData.meta_key = 'surface'
	INNER JOIN wp_postmeta as roomsData ON roomsData.post_id = post.ID AND roomsData.meta_key = 'bedrooms_count'
	INNER JOIN wp_postmeta as bathRoomsData ON bathRoomsData.post_id = post.ID AND bathroomsData.meta_key = 'bathrooms_count'
	INNER JOIN wp_postmeta as typeData ON typeData.post_id = post.ID AND typeData.meta_key = 'type'
	INNER JOIN wp_postmeta as img_meta ON img_meta.post_id = post.ID AND img_meta.meta_key = 'coverImage'
 WHERE POST.post_type = 'room'
 GROUP BY POST.ID
);
```

### Table `reviews` (19 700 lignes)

```SQL
CREATE TABLE `reviews` (
	`id` bigint(255) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`id_hotel` bigint(255) NOT NULL,
	`review` int NOT NULL,
	FOREIGN KEY (`id_hotel`) REFERENCES `hotels`(`id`);
);
```

```SQL
-- REQ SQL INSERTION DONNÉES DANS LA TABLE
```


## Question 13 : Implémentation d'un cache Redis

**Temps de chargement de la page**

| Sans Cache | Avec Cache |
|------------|------------|
| TEMPS      | TEMPS      |
[URL pour ignorer le cache sur localhost](http://localhost?skip_cache)

## Question 14 : Compression GZIP

**Comparaison des poids de fichier avec et sans compression GZIP**

|                       | Sans  | Avec  |
|-----------------------|-------|-------|
| Total des fichiers JS | POIDS | POIDS |
| `lodash.js`           | POIDS | POIDS |

## Question 15 : Cache HTTP fichiers statiques

**Poids transféré de la page**

- **Avant** : POIDS
- **Après** : POIDS

## Question 17 : Cache NGINX

**Temps de chargement cache FastCGI**

- **Avant** : TEMPS
- **Après** : TEMPS

#### Que se passe-t-il si on actualise la page après avoir coupé la base de données ?

REPONSE

#### Pourquoi ?

REPONSE
