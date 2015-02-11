<?php

if(count($argv) == 5) {
	$base_url = $argv[1];
	if(strpos($base_url, "://") === false) { $base_url = "http://$base_url"; }
	
	if(!(isset($base_url)) && $base_url == '') {
		echo 'Pleaese specify URL of site';
		exit();
		
	} else if(!filter_var($base_url, FILTER_VALIDATE_URL)) {
		echo 'Pleaese specify valid URL'; 
		exit();
	}
	
	if(!filter_var($argv[2], FILTER_VALIDATE_INT)) {
		echo 'Second argument must be an integer'; 
		exit();
	}
	
	if(!filter_var($argv[3], FILTER_VALIDATE_INT)) {
		echo 'Third argument must be an integer'; 
		exit();
	}
	
	if($argv[3] <= $argv[2]) {
		echo 'Third argument must be greater than second argument'; 
		exit();
	}
	
	if(!(isset($argv[4])) && $argv[4] == '') {
		echo 'Please specify CSV file name'; 
		exit;
	}
	
	$start_id = $argv[2];
	$end_id = $argv[3];
	$csv_filename = $argv[4];
	$range = $end_id-$start_id;
	
} else {
	echo 'Error in passing arguments.Arguments must be in order <site_name> <start_page_id> <end_page_id> <csv_file_name>'; 
	exit();
}

include_once 'simple_dom.php';
$items = array();
$count = 1;
$fp = '';
$filename = $csv_filename.'_'.$start_id.'.csv';
$fp = fopen($filename, 'a');

//CSV headers
$header = array( '#', 'Source URL', 'Company name', 'Category Tree', 'Address', 'City', 'Region', 'Phone', 'Mobile Phone', 'Fax', 'Website', 
	'Latitude', 'Longitude', 'Description', 'Keywords', 'Business Activities', 'No of reviews', 'Star ratings', 'Opening hours', 'Registration code',
	'VAT registration', 'Establishment year', 'Company manager', 'Employees', 'Logo Image Url', 'Product Details', 'Photos' );

if(file_exists($filename)) {
	//open CSV file  
	if($fp) {
		//convert headers into csv format
		fputcsv($fp, $header, ',', '"');	
	}
}
$write_count = 1;
$log_filename = $csv_filename.'_'.$start_id.'_error_log.txt';
$write_count = 1;
for($i = $start_id; $i < $end_id; $i++) {
	
	if($i % 10000 == 0) {
		//CSV filename
		fclose($fp);
		$count = 1;
		$filename = $csv_filename.'_'.$i.'.csv'; 
		$fp = fopen($filename, 'a');
		
		//CSV headers
		$new_header = array( '#', 'Source URL', 'Company name', 'Category Tree', 'Address', 'City', 'Region', 'Phone', 'Mobile Phone', 'Fax', 'Website', 
			'Latitude', 'Longitude', 'Description', 'Keywords', 'Business Activities', 'No of reviews', 'Star ratings', 'Opening hours', 'Registration code',
			'VAT registration', 'Establishment year', 'Company manager', 'Employees', 'Logo Image Url', 'Product Details', 'Photos' );
		
		if(file_exists($filename)) {
			//open CSV file  
			//$fp = fopen($filename, 'a');
			
			if($fp) {
				//convert headers into csv format
				fputcsv($fp, $new_header, ',', '"');	
			}
		}
	} 

			$url = $base_url.'/company/'.$i;
			$html = file_get_html($url, $log_filename);
			
			if($html) {
				try {
					$logo_url = '';
					//Company catgeory tree
					$category_lists = $html->find('div[class=toppath]');
					foreach ($category_lists as $category_list) {
						
						$category_tree_list = strip_tags($category_list->innertext);
						$category_tree_list = explode('&raquo;', strip_tags($category_list->innertext));
						$city = $category_tree_list[1];
						$region = $category_tree_list[0];
						$category_tree = html_entity_decode(str_replace('&raquo;', '>>', strip_tags($category_list->innertext)));
					}
					unset($category_lists);
					
					//Company Photos
					$photo_count = count($html->find('div[id=company_item] div[id=company_details] div[class=company_photos] a'));
					
					for($l = 0; $l < $photo_count; $l++ ) {
						$photo_list = $html->find('div[id=company_item] div[id=company_details] div[class=company_photos] a', $l);
						if($photo_list->getAttribute('href') != '#' && $photo_list->getAttribute('href') != '') {
							$company_photos[$l] = $photo_list->getAttribute('href'); 
						}
					}
					if(isset($company_photos) && count($company_photos) > 0 ) { 
						$photos = implode(',', $company_photos); 
					}		
					unset($company_photos);
				
					//Product Details
					$product_count = '';
					$is_more_products = $html->find('div[id=company_item] div[class=company_item_center] div[class=info] div a[class=more]');
					foreach($is_more_products  as $is_more_product) {
						preg_match('!\d+!', $is_more_product->innertext, $matches);
						$product_count = $matches[0];
						$product_url = $is_more_product->getAttribute('href');
						$product_url = $base_url.$product_url;
						$product_html = file_get_html(encodeURI($product_url), $log_filename);
					} 
					
					if($product_count > 3) {
						if(count($product_html->find('div[id=right] div[class=product]'))) {
							for($pcount = 0; $pcount < $product_count; $pcount++) {	
								if( $product_count < 10) {
									//Product Title
									$product_list_titles = $product_html->find('div[id=right] div[class=product]', $pcount)->find('div[class=product_name] a'); 
									foreach($product_list_titles as $product_list_title) {
								 		$product_title[$pcount]  = $product_list_title->plaintext;
									}
										
									//Product Description
									$product_list_descriptions = $product_html->find('div[id=right] div[class=product]', $pcount);
									$product_description[$pcount] = preg_replace('/<div\sclass=\"product_name\">[^<]+<\/div>/i', '', strip_tags($product_list_descriptions->innertext, '<div>')); 
									
									//Product Image
									$product_list_logos = $product_html->find('div[id=right] div[class=product]', $pcount)->children(0);
									$product_list_image = $product_list_logos->innertext;
									preg_match( '/src="([^"]*)"/i', $product_list_image, $product_image_matches ) ;
									$url_parse  = parse_url($url);
									if(isset($product_image_matches[1])) {
										$product_image[$pcount] = $product_image_matches[1];
									}
									else {
										$product_image[$pcount] = '';
									}
								}
								
							}
						} else {
							for($pcount = 0; $pcount < $product_count; $pcount++) {	
								if( $product_count < 10) {
									//Product Title
									$product_list_titles = $product_html->find('div[id=articles] div[class=company]', $pcount)->find('div[class=articles_content] h2 a'); 
									foreach($product_list_titles as $product_list_title) {
								 		$product_title[$pcount]  = $product_list_title->plaintext;
									}

									//Product Description
									$product_list_descriptions = $product_html->find('div[id=articles] div[class=company]', $pcount)->find('div[class=articles_content]');
									foreach($product_list_descriptions as $product_list_description) {
										$product_description[$pcount] = preg_replace('/<div>[^<]+<\/div>/i', '', strip_tags($product_list_description->innertext, '<div>'));
									}
									
									//Product Image
									$product_list_logos = $product_html->find('div[id=articles] div[class=company] div[class=articles_image]', $pcount)->children(0);
									$product_list_image = $product_list_logos->innertext;
									preg_match( '/src="([^"]*)"/i', $product_list_image, $product_image_matches ) ;
									$url_parse  = parse_url($url);
									if(isset($product_image_matches[1])) {
										$product_image[$pcount] = $product_image_matches[1];
									}
									else {
										$product_image[$pcount] = '';
									}
								}
								
							}
						}
						
						if(isset($product_title) && count($product_title) > 0) {
							$product_details = implode('&^%$*', $product_title).'||'.implode('&^%$*', $product_description).'||'.implode('&^%$*', $product_image);
						}
						
					} else {
						
						//Product Details
						$product_count = count($html->find('div[id=company_item] div[class=company_item_center] div[class=info] div[class=product]')); 
						if($product_count > 0 ) {
							for($pcount = 0; $pcount < $product_count; $pcount++) {
								
								//Product Title
								$product_list_titles = $html->find('div[id=company_item] div[class=company_item_center] div[class=info] div[class=product]', $pcount)->find('div[class=product_name] a'); 
								foreach($product_list_titles as $product_list_title) {
							 		$product_title[$pcount]  = $product_list_title->plaintext;
								}
					
								//Product Description
								$product_list_descriptions = $html->find('div[id=company_item] div[class=company_item_center] div[class=info] div[class=product]', $pcount);
								$product_description[$pcount] = preg_replace('/<div\sclass=\"product_name\">[^<]+<\/div>/i', '', strip_tags($product_list_descriptions->innertext, '<div>')); 
								
								//Product Image
								$product_list_logos = $html->find('div[id=company_item] div[class=company_item_center] div[class=info] div[class=product]', $pcount)->children(0);
								$product_list_image = $product_list_logos->innertext;
								preg_match( '/src="([^"]*)"/i', $product_list_image, $product_image_matches ) ;
								$url_parse  = parse_url($url);
								if(isset($product_image_matches[1])) {
									$product_image[$pcount] = $product_image_matches[1];
								}
								else {
									$product_image[$pcount] = '';
								}
							}
						}
						if(isset($product_title) && count($product_title) > 0) {
							$product_details = implode('&^%$*', $product_title).'||'.implode('&^%$*', $product_description).'||'.implode('&^%$*', $product_image);
						}
					}
					
					unset($product_count);
					unset($product_list_titles);
					unset($product_title);
					unset($product_description);
					unset($product_image);
					
					//Company review count
					$company_navs = $html->find('div[id=company_nav] ul li a[class=nav_reviews]');
					foreach ($company_navs as $company_nav) {
						$review_count = preg_match('#\((.*?)\)#', $company_nav->innertext, $review_match);
						$review_count = $review_match[1];
					}
					unset($company_navs);
					
					//Company Details
					$company_contacts = $html->find('div[id=company_details] div[class=info]');
					foreach ($company_contacts as $company_contact) {
						
						if($company_contact->first_child()->plaintext == 'Company name') {
							$company_name =  htmlspecialchars_decode(preg_replace('#<div class="label">(.*?)</div>#', ' ', $company_contact->innertext));
						} 
						if($company_contact->first_child()->plaintext == 'Address') {
							$address = strip_tags($company_contact->children(1)->innertext);
						} 
						if($company_contact->first_child()->plaintext == 'Phone') {
							$phone = substr(str_replace('<br/>', '||', $company_contact->children(1)->innertext), 0, -2);
						} 
						if($company_contact->first_child()->plaintext == 'Mobile phone') {
							$mobile = strip_tags(substr(str_replace('<br/>', '||', $company_contact->children(1)->innertext), 0, -2));
						} 
						if($company_contact->first_child()->plaintext == 'Fax') {
							$fax = strip_tags(substr(str_replace('<br/>', '||', $company_contact->children(1)->innertext), 0, -2));
						}
						if($company_contact->first_child()->plaintext == 'Website') {
							$website = strip_tags($company_contact->children(1)->innertext);
						}
						
					}
					unset($company_contacts);
					
					//Company Coordinates & description 
					$company_details = $html->find('div[id=company_item] div[class=company_item_center] div[class=info]');
					foreach ($company_details as $company_description) {
						//Company Location co-ordinates
						if($company_description->first_child()->plaintext == 'Location map') {
							preg_match_all("/(?<=google.maps.LatLng\()(.*?)(?=\))/s", $company_description->innertext, $matches);
							if(is_array($matches[1]) && count($matches[1]) > 0 ) {
								$map_coordinates =  $matches[1];
								$map_coordinate = explode(',', $map_coordinates[0]);
							} else {
								$map_coordinatep[0] = '';
								$map_coordinatep[1] = '';
							}
						}
						//Company Description
						$company_descriptions = $html->find('div[id=company_item] div[class=company_item_center] div[class=info] div[class=description]');
						foreach($company_descriptions as $company_description) {
							$description = html_entity_decode($company_description->plaintext, ENT_QUOTES, 'UTF-8');
						}
						unset($company_descriptions);
					}
					
					//Opening Hours
					$company_opening_hours = $html->find('div[id=company_item] div[class=company_item_center] div[class=info] ul[class=openinghours]');
					foreach ($company_opening_hours as $company_opening_hour) {
						$company_opening = preg_replace('#\s(id|class)="[^"]+"#', '', $company_opening_hour->innertext);
						$opening_hours_list = explode("<li>", $company_opening);
						unset($opening_hours_list[0]);
						array_walk( $opening_hours_list, function( &$opening_hours_value, $opening_hours_key) { $opening_hours_value = strip_tags($opening_hours_value); });
						$working_hours = implode('||', $opening_hours_list);
					}
					unset($company_opening_hours);
					
					//Business Activity
					foreach ($company_details as $company_category) {	
						if($company_category->first_child()->plaintext == 'Listed in categories') {
							$category_list = $company_category->innertext;
							$categories = preg_replace('#<div class="label">(.*?)</div>#', ' ', $category_list);
							$categories = trim(strip_tags($categories, "<br/><br>"));
							$category = htmlspecialchars_decode(substr(str_replace('<br/>', '||', $categories), 0, -2));
						}
					}
					
					unset($company_details);
					
					//Company keywords
					$company_keywords = $html->find('div[id=company_item] div[class=company_item_center] div[class=info] ul[class=tags]');
					foreach ($company_keywords as $company_keyword) {
						$keywords_list = explode("<li>", $company_keyword->innertext);
						unset($keywords_list[0]);
						array_walk( $keywords_list, function( &$keywords_value, $keywords_key) { $keywords_value = strip_tags($keywords_value); });
						$keywords = implode('||', $keywords_list);						
					}
					unset($company_keywords);
					
					$company_establishments = $html->find('div[id=company_item] div[class=company_item_center] div[class=info] div[class=shortinfo]'); 
					foreach($company_establishments as $company_establishment) {
						
						//Registration code
						if($company_establishment->first_child()->plaintext == 'Registration code') {
							$registration_code = $company_establishment->children(1)->innertext;
						}
						
						//VAT code
						if($company_establishment->first_child()->plaintext == 'VAT registration') {
							$vat_registartion = $company_establishment->children(1)->innertext;
						}
						
						
						//Establishment year
						if($company_establishment->first_child()->plaintext == 'Establishment year') {
							$established_year = $company_establishment->children(1)->innertext;
						}
						//Company manager
						if($company_establishment->first_child()->plaintext == 'Company manager') {
							$company_manager = $company_establishment->children(1)->innertext;
						}
							
						//Employees Count
						if($company_establishment->first_child()->plaintext == 'Employees') {
							$employees = $company_establishment->children(1)->innertext;
						}
					}
					unset($company_establishments);
					
					//Star Rating 
					$company_ratings = $html->find('div[class=general_rate] span[class=rate]');
					foreach ($company_ratings as $company_rating) {	
						$rating = strip_tags($company_rating->plaintext);
					}
					unset($company_ratings);
				} catch(Exception $message) {
					 $error_fp = fopen($log_filename, 'a');
					 fwrite($error_fp, $message->getMessage().PHP_EOL);
					 fclose($error_fp);
				}
	
				if(isset($company_name) && $company_name != '') {
					
					//if($write_count % 5 == 0 )
					
					$items[$write_count]['sl'] = $count;
					$items[$write_count]['source_url'] = $url;
					$items[$write_count]['company_name'] = (isset($company_name)) ? $company_name: '';
					$items[$write_count]['category_tree'] = (isset($category_tree)) ? $category_tree: '';  
					$items[$write_count]['address'] = (isset($address)) ? $address : '';
					$items[$write_count]['city'] = (isset($city)) ? $city : '';
					$items[$write_count]['region'] = (isset($region)) ? $region : '';
					$items[$write_count]['phone'] = (isset($phone)) ? $phone : '';
					$items[$write_count]['mobile_phone'] = (isset($mobile)) ? $mobile : '';
					$items[$write_count]['fax'] = (isset($fax)) ? $fax : '';
					$items[$write_count]['website'] = (isset($website)) ? $website : '';
					$items[$write_count]['lat'] = (isset($map_coordinate) && count($map_coordinate) > 0 ) ? $map_coordinate[0] : '';
					$items[$write_count]['lon'] = (isset($map_coordinate) && count($map_coordinate) > 0 ) ? $map_coordinate[1] : '';
					$items[$write_count]['description'] = (isset($description)) ? $description : '';
					$items[$write_count]['keywords'] = (isset($keywords)) ? $keywords : '';
					$items[$write_count]['business_activities'] = (isset($category)) ? $category : '';
					$items[$write_count]['no_reviews'] = (isset($review_count)) ? $review_count : 0;
					$items[$write_count]['star_rating'] = (isset($rating)) ? $rating : '';
					$items[$write_count]['working_hours'] = (isset($working_hours)) ? $working_hours : '';
					$items[$write_count]['registration_code'] = (isset($registration_code)) ? $registration_code : '';
					$items[$write_count]['vat_registartion'] = (isset($vat_registartion)) ? $vat_registartion : '';
					$items[$write_count]['establishment_year'] = (isset($established_year)) ? $established_year : '';
					$items[$write_count]['company_manager'] = (isset($company_manager)) ? $company_manager : '';
					$items[$write_count]['employees'] = (isset($employees)) ? $employees : '';
					$items[$write_count]['logo_url'] = (isset($logo_url)) ? $logo_url : '';
					$items[$write_count]['product_details'] = (isset($product_details) && count($product_details) ) ? $product_details : '';
					$items[$write_count]['photos'] = (isset($photos) && count($photos) > 0 ) ? $photos : '';
		
					$write_count++;
					$count++;
				} else {
					$message = "URL ID :".basename($url).PHP_EOL;
					$tor_log_filename = $csv_filename.'_log.txt';
					$log_fp = fopen($tor_log_filename, 'a');
					fwrite($log_fp, $message);
					fclose($log_fp);			
					shell_exec('./ipchanger');
					sleep(10);
				}
				if($write_count % 10 == 0) {
					//open CSV file  
					if($fp) {
						foreach($items as $item) {
							fputcsv($fp, $item);
						}
					}
					$write_count = 1;
					unset($items);
				}
			}
			
			sleep(3);
			
			unset($company_name);
			unset($category_tree);
			unset($address);
			unset($city);
			unset($region);
			
			unset($phone);
			unset($mobile);
			unset($fax);
			unset($website);
			unset($map_coordinate);
			unset($description);
			unset($keywords);
			unset($category);
			unset($review_count);
			unset($rating);
			unset($working_hours);
			unset($registration_code);
			unset($vat_registartion);
			unset($established_year);
			unset($company_manager);
			unset($employees);
			unset($logo_url);
			unset($photos);
			unset($product_details);
			unset($html);
}
if(count($items) > 0 ) {
	if($fp) {
		foreach($items as $item) {
			fputcsv($fp, $item);
		}
	}
	$write_count = 1;
	unset($items);
}
echo 'Success : CSV File '.$filename.' generated successfully';
fclose($fp);

function encodeURI($url) {
    // http://php.net/manual/en/function.rawurlencode.php
    // https://developer.mozilla.org/en/JavaScript/Reference/Global_Objects/encodeURI
    $unescaped = array(
        '%2D'=>'-','%5F'=>'_','%2E'=>'.','%21'=>'!', '%7E'=>'~',
        '%2A'=>'*', '%27'=>"'", '%28'=>'(', '%29'=>')'
    );
    $reserved = array(
        '%3B'=>';','%2C'=>',','%2F'=>'/','%3F'=>'?','%3A'=>':',
        '%40'=>'@','%26'=>'&','%3D'=>'=','%2B'=>'+','%24'=>'$'
    );
    $score = array(
        '%23'=>'#'
    );
    return strtr(rawurlencode($url), array_merge($reserved,$unescaped,$score));

}
?>