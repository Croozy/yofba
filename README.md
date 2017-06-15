# YOFBA (Your Own Facebook Album)

<br/>

## Demo
* Facebook Album : [https://www.facebook.com/pg/Croozy-288111188298918/photos/?tab=album&album_id=288111494965554][url_demo_FacebookAlbum]
* YOFBA : [https://demo.croozy.io/yofba/?token=MyPrivateToken][url_demo_yofba]
* YOFBA's JSON : [https://demo.croozy.io/yofba?getAlbum=288111494965554][url_demo_yofbaJSON]
* Photoswipe Gallery than use YOFBA : [https://demo.croozy.io/yofba/yofba_gallery_photoswipe?album-id=288111494965554][url_demo_galleryWithYofba]

<br/>

## Features
* Customization :
  * Allow to create a custom JSON feed based on Facebook albums.
  * Then, you can use this JSON feed to create a photo gallery. You can use all html/javascript photo galleries.
* Optimisation : Create a cache (containing the JSON) to increase the response time.
* Simplicity : For its implementation/configuration (This is why this project is in one file
).

<br/>

## How it works ?

### yofba.php
 1. Get and [configure](#configuration) this file
 2. Put on your server
 3. Run code
 4. [JSONs][url_demo_yofbaJSON] have been created

### YourPhotoGallery.php
1. Read JSON feed on yofba.php?getAlbum=%youralbumId%

#### Examples
[Warning](#warning_your_photo_gallery)
##### jQuery
```javascript
var urlToCall = "yofba.php?getAlbum=" + "%youralbumId%";
$.getJSON(urlToCall, function (data) {
  $("#album-name").text(data["album"]["name"]);
  var albumLink = data["album"]["link"];

  [...]

  //Iterate on each photo of the album
  $.each(data["photos"]["data"], function () {
    var imageFullSize = this["images"][0];
  });
}
```

#### <a id="warning_your_photo_gallery"></a>Warning
If your photo gallery and yofba.php are not on the same domain :  
Make sure (in yofba.php) that the [CORS calls are allowed for your domain](#Config-property-app_allow_cors_request_from_url).

<br/>
<br/>

---

<br/>

## <a id="configuration"></a>Configuration

### 1. Make Facebook App

#### Step 1 : Add a New App
Create a new app on : https://developers.facebook.com/apps
#### <a id="ConfigFacebookStep2"></a>Step 2 : Get your App ID and App secret
![alt text][img_FBSettings]

### 2. Configure yofba.php (class : Config)

If a property is commenting "Customize" it is that it must be replaced by your values. These properties are shown **in bold** below.

* **fb_app_id** :
Get your App ID in [Step 2](#ConfigFacebookStep2)

* **fb_app_secret** :
Get your App Secret in [Step 2](#ConfigFacebookStep2)

* app_fb_json_albums_file_path :
Location where the JSONs are stored

* **app_fb_albums_array** :
List of all albums facebook has recovered. ID and name (for UI drop-down list) are required. To get the ID of an album, retrieve the "album_id" parameter from the [URL][url_demo_FacebookAlbum].

* app_debug_mode :
If you want debug this app. Use Tools::showMessage(" ",MessageType::Debug) method.

* **app_token** :
To secure access.

* app_client_access_number_of_attempts_before_the_tempory_ban_of_the_ip :
Maximum number of access attempts in app_client_access_time_between_new_try time

* app_client_access_number_of_attempts_before_definitely_ban :
Maximum number of access attempts failed

* app_client_access_time_between_new_try :
Time between access attempts.

* app_client_access_file_path :
Location where the client acces are stored.

* app_list_of_fields_album :
List of all the Facebook fields you want to retrieve for an album (doc : https://developers.facebook.com/docs/graph-api/reference/album).

* app_list_of_fields_photos_album :
List of all Facebook fields you want to retrieve on album photos (doc : https://developers.facebook.com/docs/graph-api/reference/photo)

* app_count_facebook_likes :
Count the numbers of likes.

* app_count_facebook_likes_field_name :
Name of JSON field for likes counter.

* app_get_main_color_of_image :
Analyse and get main color of an image.

* app_get_main_color_of_image_field_name :
Name of JSON field for main color.

* app_exception_file_path :
Location where esceptions are stored.

* <a id="Config-property-app_allow_cors_request_from_url"></a>app_allow_cors_request_from_url :
If you want to call the JSONs from another domain, you must fill in all the calling domains.  
Example :  
  * yofba.php in subdomain app.yourSite.com  
  * yourPhotoGallery_1.php in subdomain photos.yourSite.com  
  * yourPhotoGallery_2.php in domain yourSite.com   
The app_allow_cors_request_from_url property in yofba.php should look like this :
```php
const app_allow_cors_request_from_url = array("https://photos.yourSite.com","https://www.photos.yourSite.com", "https://yourSite.com","https://www.yourSite.com");
```

<br/>

## Optional
### CRON
Automate the update of JSONs
#### Update all albums : cron.sh
```bash
#!/bin/sh
/usr/local/php7.1/bin/php /xxx/xxx/demo/yofba/index.php token=yourToken
```
Or
```bash
#!/bin/sh
cd /xxx/xxx/subdomain/ && /usr/local/php7.0/bin/php -f yofba.php token=yourToken
```


[img_FBSettings]: https://cloud.githubusercontent.com/assets/28596207/25900677/8a43c528-3594-11e7-9aab-6e5353f88219.PNG "Facebook settings : App ID and App Secret"

[url_demo_FacebookAlbum]:https://www.facebook.com/pg/Croozy-288111188298918/photos/?tab=album&album_id=288111494965554
[url_demo_yofba]:https://demo.croozy.io/yofba/?token=MyPrivateToken
[url_demo_galleryWithYofba]:https://demo.croozy.io/yofba/yofba_gallery_photoswipe?album-id=288111494965554
[url_demo_yofbaJSON]:https://demo.croozy.io/yofba?getAlbum=288111494965554
