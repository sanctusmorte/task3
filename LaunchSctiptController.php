<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Category;
use App\Product;
use App\Characteristic;
use App\Proxy;
use duzun\hQuery;
use CRUDBooster;
use Exception;

class LaunchSctiptController extends Controller
{

    public function index()
    {
    	$this->fillCategories();
    	$this->fillProductsCharacteristics(); 
        
    } 	

	/*
 	* Функция инициализирует CURL сеанс
 	* @param 
 		$curlopt_url - url ссылка на страницу
 		$curlopt_useragent - useragent для CURLOPT_USERAGENT
 		$curlopt_cookiesfile - файл кук для CURLOPT_COOKIEFILE/CURLOPT_COOKIEJAR
 		$curl_headers - массив с заголовками для CURLOPT_HTTPHEADER
 	* @return string
 	*/    

    public function curl($curlopt_url, $curlopt_useragent, $curlopt_cookiesfile, $curl_headers )
    {
    	$proxies = $this->proxies();

    	If ( count($proxies) > 1 ) {

    		foreach ( $proxies as $proxie )
    		{
		    	$ch = curl_init();
			    curl_setopt($ch, CURLOPT_URL, $curlopt_url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
				curl_setopt($ch, CURLOPT_ENCODING, '');
				curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
				curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANYSAFE);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		    	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
				curl_setopt($ch, CURLOPT_USERAGENT, $curlopt_useragent);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
				curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiesFile);
				curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiesFile);	

				If ( $proxie['data'] ) {
					curl_setopt($ch, CURLOPT_PROXY, $proxie);
				}

				$result = curl_exec($ch);
				$curl_error_msg = curl_error($ch);
				curl_close($ch);

				If ( $curl_error_msg != "" ) {
					$delete_proxy = Proxy::where('data', $proxie['data'])->delete();
            	    $config['content'] = 'Прокси  '.$proxie['data'].' не валидный, был удален из бд';
            	    CRUDBooster::sendNotification($config); 					
					return false;
				} else {
					return $result;
					break;
				}  
			}

    	} else {
            $config['content'] = 'Прокси больше не осталось, останавливаем скрипт';
            CRUDBooster::sendNotification($config);    	    
    	}
    	
    }

	/*
 	* Функция вытаскивает из бд все прокси
 	* @return array
 	*/    

    public function proxies()
    {
    	$proxies = Proxy::all();
    	return $proxies;
    }

	/*
 	* Функция создает массив ссылок
 	* @return array
 	*/    

    public function curloptUrls()
    {
    	$curlopt_urls = [];

    	for ( $i = 1; $i < 4; $i++ )
    	{
    		array_push($curlopt_urls, 'https://www.olx.ua/transport/legkovye-avtomobili/vinnitsa/?page='.$i.'');
    	}

    	return $curlopt_urls;
    }

	/*
 	* Функция возвращает рандомную строку из массива
 	* @return string
 	*/    
    
    public function curloptUseragent()
    {
    	$curlopt_useragents = [
    		'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:64.0) Gecko/20100101 Firefox/64.0',
    		'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/71.0.3578.98 Safari/537.36',
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.246'    	
    	];

    	return $curlopt_useragents[array_rand($curlopt_useragents)];
    }


	/*
 	* Функция возвращает массив заголовков
 	* @return array
 	*/       

    public function curloptHeaders()
    {
    	$curl_headers = [
			'Accept' => 'text/html,application/xhtml+xm…plication/xml;q=0.9,*/*;q=0.8',
			'Accept-Encoding' => 'gzip, deflate, br',
			'Accept-Language' => 'ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
			'Connection' => 'keep-alive',
			'Content-Type' => 'application/x-www-form-urlencoded',
			'Host' => 'www.olx.ua',
			'Referer' => 'https://www.aeroplan.com/adr/SearchProcess.do',
			'TE' => 'Trailers',
			'Upgrade-Insecure-Requests' => '1'
		];    	

		return $curl_headers;
    }

	/*
 	* Функция парсит названия каталогов и заносит их в бд
 	*/   

    public function fillCategories()
    {
    	// ссылка
    	$curlopt_url = 'https://www.olx.ua/transport/legkovye-avtomobili/vinnitsa';

    	// useragent
    	$curlopt_useragent = $this->curloptUseragent();

    	// кукифайл
    	$curlopt_cookiesfile = 'olx.txt';

    	// заголовки headers
    	$curl_headers = $this->curloptHeaders();

	    //////////////////////////////////////////////////////////////////////
	    // парсим главную страницу, далее разбираем html при помощи simple_html_dom
	    // вытаскиваем названия всех категорий и добавляем в бд
	    //////////////////////////////////////////////////////////////////////

    	$result = $this->curl( $curlopt_url, $curlopt_useragent, $curlopt_cookiesfile, $curl_headers );


    	try {
    	    
        	If ( !$result ) {
        		throw new Exception('empty variable $result, function fillCategories() has been stopped');
        	}    	    

	    	$html = hQuery::fromHTML($result);

	 		$elements = $html->find('#topLink .inner ul li a span span');

	 		foreach ($elements as $element) {
	 			$category = Category::firstOrCreate( ['title' => $element] );
	 			$category->save();
	 		}
 		} catch (Exception $d) {
		  	echo 'Exception is thrown: ',  $d->getMessage(), "\n" . "<br><br>";
		    } 
    } 

	/*
 	* Функция парсит все товары и характеристики к каждому товары и заносит всё в бд
 	*/       

    public function fillProductsCharacteristics()
    {
    	// массив ссылка
    	$curlopt_urls = $this->curloptUrls();

    	// useragent
    	$curlopt_useragent = $this->curloptUseragent();

    	// кукифайл
    	$curlopt_cookiesfile = 'olx.txt';

    	// заголовки headers
    	$curl_headers = $this->curloptHeaders();


    	foreach ( $curlopt_urls as $curlopt_url )
    	{

    		$result = $this->curl( $curlopt_url, $curlopt_useragent, $curlopt_cookiesfile, $curl_headers );


    		try {
    		    
        		If ( !$result ) {
        			throw new Exception('empty variable $result, function fillProductsCharacteristics() has been stopped');
        		}    		    

	    		$html = hQuery::fromHTML($result);
	    		$products = $html->find('#offers_table tbody .wrap');

	    		foreach ($products as $product) 
	    		{

	    			If ( $product->find('.fixed tbody tr a:detailsLink img') != null ) 
	    			{
	    				$img = $product->find('.fixed tbody tr a:detailsLink img')->attr('src');
	    			} else {
	    				$img = "Нет фото";
	    			}
	    			$url = $product->find('.fixed tbody a:thumb')->attr('href');
	    			$title = $product->find('.fixed tbody tr .title-cell .lheight22 strong');
	    			$producer = trim(mb_substr( $product->find('.fixed tbody tr .title-cell .breadcrumb')->text(), 78, 100 ));
	    			$address = $product->find('.fixed tbody .lheight16 .breadcrumb span:first-child')->text();
	    			$date = $product->find('.fixed tbody .lheight16 .breadcrumb span:last-child')->text();
	    			$price = $product->find('.fixed tbody tr .td-price .price')->text();
	    	
	    			$result_characteristics = $this->curl( $curlopt_url = $url, $curlopt_useragent, $curlopt_cookiesfile, $curl_headers );



	    			try 
	    			{
	    			    
    	    			If ( !$result_characteristics ) {
    	    				throw new Exception('empty variable $result_characteristics, function fillProductsCharacteristics() has been stopped');
    	    			}	    
    	    			
		    			$html_characteristics = hQuery::fromHTML($result_characteristics);
		    			$characteristics = $html_characteristics->find('.descriptioncontent .details');

		    			foreach ( $characteristics as $characteristic )
		    			{
		    				$url_char = $curlopt_url;

			    			If ( $characteristic->find('tr:1 .value a:first-child') != null ) 
			    			{
			    				$announcement_from = $characteristic->find('tr:1 .value a:first-child')->text();
			    			} else {
			    				$announcement_from = "Не указано";
			    			}    	

			    			If ( $characteristic->find('tr:3 .value a:first-child') != null ) 
			    			{
			    				$model = $characteristic->find('tr:3 .value a:first-child')->text();
			    			} else {
			    				$model = "Не указано";
			    			}  

			    			If ( $characteristic->find('tr:6 .value a:first-child') != null ) 
			    			{
			    				$body_type = $characteristic->find('tr:6 .value a:first-child')->text();
			    			} else {
			    				$body_type = "Не указано";
			    			}   

			    			If ( $characteristic->find('tr:9 .value a:first-child') != null ) 
			    			{
			    				$fuel_type = $characteristic->find('tr:9 .value a:first-child')->text();
			    			} else {
			    				$fuel_type = "Не указано";
			    			}  	

			    			If ( $characteristic->find('tr:12 .value a:first-child') != null ) 
			    			{
			    				$gearbox = $characteristic->find('tr:12 .value a:first-child')->text();
			    			} else {
			    				$gearbox = "Не указано";
			    			}  		

			    			If ( $characteristic->find('tr:16 .value a') != null ) 
			    			{
			    				$condition_car = $characteristic->find('tr:16 .value a')->text();
			    			} else {
			    				$condition_car = "Не указано";
			    			}  	

			    			If ( $characteristic->find('tr:19 .value a') != null ) 
			    			{
			    				$multimedia = $characteristic->find('tr:19 .value a')->text();
			    			} else {
			    				$multimedia = "Не указано";
			    			}  		 

			    			If ( $characteristic->find('tr:22 .value a') != null ) 
			    			{
			    				$other = $characteristic->find('tr:22 .value a')->text();
			    			} else {
			    				$other = "Не указано";
			    			}

			    			If ( $characteristic->find('tr:2 .value a') != null ) 
			    			{
			    				$mark = $characteristic->find('tr:2 .value a')->text();
			    			} else {
			    				$mark = "Не указано";	   
			    			}

			    			If ( $characteristic->find('tr:5 strong') != null ) 
			    			{
			    				$issue_year = $characteristic->find('tr:5 strong')->text();
			    			} else {
			    				$issue_year = "Не указано";
			    			}

			    			If ( $characteristic->find('tr:8 .value a') != null ) 
			    			{
			    				$color = $characteristic->find('tr:8 .value a')->text();
			    			} else {
			    				$color = "Не указано";
			    			}

			    			If ( $characteristic->find('tr:11 strong') != null ) 
			    			{
			    				$capacity_engine = $characteristic->find('tr:11 strong')->text();
			    			} else {
			    				$capacity_engine = "Не указано";	
			    			}

			    			If ( $characteristic->find('tr:14 strong') != null ) 
			    			{
			    				$mileage = $characteristic->find('tr:14 strong')->text();
			    			} else {
			    				$mileage = "Не указано";
			    			}

			    			If ( $characteristic->find('tr:17 .value a') != null ) 
			    			{
			    				$additional_opt = $characteristic->find('tr:17 .value a')->text();
			    			} else {
			    				$additional_opt = "Не указано";	  
			    			}

			    			If ( $characteristic->find('tr:20 .value a') != null ) 
			    			{
			    				$security = $characteristic->find('tr:20 .value a')->text();
			    			} else {
			    				$security = "Не указано";	 	   
			    			}

			    			If ( $characteristic->find('tr:23 .value a') != null ) 
			    			{
			    				$customs_cleared = $characteristic->find('tr:23 .value a')->text();
			    			} else {
			    				$customs_cleared = "Не указано";
			    			}		    				 				  					    				    					    					    				 				


			    			If ( Characteristic::where('url', $curlopt_url)->first() === null  )
			    			{
			    				$char = new Characteristic;
			    				$char->url = $curlopt_url;
			    				$char->announcement_from = $announcement_from;
			    				$char->model = $model;
			    				$char->body_type = $body_type;
			    			 	$char->fuel_type = $fuel_type;
			    			 	$char->gearbox = $gearbox;
			    			  	$char->condition_car = $condition_car;
			    			   	$char->multimedia = $multimedia;
			    			   	$char->other = $other;
			    			   	$char->mark = $mark;
			    			   	$char->issue_year = $issue_year;
			    			   	$char->color = $color;
			    			   	$char->capacity_engine = $capacity_engine;
			    			   	$char->mileage = $mileage;
			    			   	$char->additional_opt = $additional_opt;
			    			   	$char->security = $security;
			    			   	$char->customs_cleared = $customs_cleared;
			    			   	$char->save();
			    			} else {
			    				continue;
			    			}
		    			}
		    		} catch (Exception $f)  {
		    					echo 'Exception is thrown: ',  $d->getMessage(), "\n" . "<br><br>";
		    				} 

		    		If ( Product::where('url', $url)->first() === null  )
		    		{
		    			$product = new Product;
		    			$product->img = $img;
		    			$product->url = $url;
		    			$product->title = $title;
		    			$product->producer = $producer;
		    			$product->address = $address;
		    			$product->date = $date;
		    			$product->price = $price;
		    			$product->save();

		    		} else {
		    			continue;
		    		}

	    		}

    		} catch (Exception $e) {
    			echo 'Exception is thrown: ',  $e->getMessage(), "\n" . "<br><br>";
    		}
    	}   	
    }
}
