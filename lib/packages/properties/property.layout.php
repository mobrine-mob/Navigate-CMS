<?php
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');

function navigate_property_layout_form($element, $template, $item, $item_id)
{
    global $website;
    global $layout;

	$out = array();
    $property_rows = array();
	
	// load the element properties
	//$properties = property::elements($element, $template);
	
	// load the item property values
	$properties = property::load_properties($element, $template, $item, $item_id);

	// generate the form
	for($p = 0; $p < count($properties); $p++)
	{
		if($properties[$p]->enabled == '0') continue;
		$property_rows[] = navigate_property_layout_field($properties[$p]);
	}

    if(!empty($property_rows) && !empty($property_rows[0]))  // no properties => no form
    {
        $out[] = '<div id="navigate-properties-form">';
        $out[] = '<input type="hidden" name="property-element" value="'.$element.'" />';
        $out[] = '<input type="hidden" name="property-template" value="'.$template.'" />';

        $property_rows = implode("\n", $property_rows);

        // language selector (only if it's a multilanguage website and we have almost one multilanguage property)
        // check --> http://192.168.1.30/navigate/navigate.php?fid=10&act=2&id=1
        if(count($website->languages) > 1 && strpos($property_rows, 'lang="') !== false)
        {
            $website_languages_selector = $website->languages();
            $website_languages_selector = array_merge(array('' => '('.t(443, 'All').')'), $website_languages_selector);

            $naviforms = new naviforms();

            $out[] = '<div class="navigate-form-row">';
            $out[] = '<label>'.t(63, 'Languages').'</label>';
            $out[] = $naviforms->buttonset('properties_language_selector', $website_languages_selector, '', "navigate_tabform_language_selector(this);");
            $out[] = '</div>';
        }

        $out[] = $property_rows;

        $out[] = '</div>';
    }
	
	return implode("\n", $out);	
}

function navigate_property_layout_field($property)
{
	global $website;
	global $layout;

	$naviforms = new naviforms();
	$langs = $website->languages_list;

	$field = array();
		
	if(!isset($property->value))
        $property->value = $property->dvalue;

	if(in_array($property->type, array("text", "textarea", "link")))
	{
		foreach($langs as $lang)
		{
			if(!isset($property->value[$lang]))
				$property->value[$lang] = $property->dvalue;
		}
	}
	
	switch($property->type)
	{
		case 'value':
			$field[] = '<div class="navigate-form-row">';
			$field[] = '<label>'.$property->name.'</label>';		
			$field[] = $naviforms->textfield("property-".$property->id, $property->value);
			$field[] = '</div>';			
			break;
			
		case 'rating':
			$default = explode('#', $property->dvalue);
			$stars = $default[1];
			if(empty($stars)) $stars = 5;
			$inputs = $stars*2; // half stars ALWAYS enabled
			
			if($property->value == $property->dvalue)
                $property->value = intval($default[0]) * 2;
		
			$field[] = '<div class="navigate-form-row" style=" height: 18px; ">';
			$field[] = '<label>'.$property->name.'</label>';
			for($i=1; $i <= $inputs; $i++)
			{		
				$checked = '';
				if($property->value == $i)
                    $checked = ' checked="checked" ';
				$field[] = '<input type="radio" name="property-'.$property->id.'" class="star {split:2}" value="'.$i.'" '.$checked.' />';
			}
			$field[] = '</div>';			
			break;			

		case 'boolean': 				
			$field[] = '<div class="navigate-form-row">';
			$field[] = '<label>'.$property->name.'</label>';		
			$field[] = $naviforms->checkbox("property-".$property->id, ($property->value=='1'));
			$field[] = '</div>';			
			break;
		
		case 'option':
            $options = $property->options;

            if(is_object($options))
                $options = (array)$options;

            if(is_string($options))
			    $options = mb_unserialize($options);

			$field[] = '<div class="navigate-form-row">';
			$field[] = '<label>'.$property->name.'</label>';			
			$field[] = $naviforms->selectfield("property-".$property->id, array_keys($options), array_values($options), $property->value);		
			$field[] = '</div>';			
			break;
			
		case 'moption': 				
			$options = mb_unserialize($property->options);
			$field[] = '<div class="navigate-form-row">';
			$field[] = '<label>'.$property->name.'</label>';			
			$field[] = $naviforms->selectfield("property-".$property->id, array_keys($options), array_values($options), explode(',', $property->value), "", true);		
			$field[] = '</div>';			
			break;			
			
		case 'country': 				
			$options = property::countries();
			$field[] = '<div class="navigate-form-row">';
			$field[] = '<label>'.$property->name.'</label>';			
			$field[] = $naviforms->selectfield("property-".$property->id, array_keys($options), array_values($options), strtoupper($property->value));		
			$field[] = '</div>';			
			break;	
			
		case 'coordinates':
			$coordinates = explode('#', $property->value);
			$latitude  = @$coordinates[0];
			$longitude = @$coordinates[1];			
			$field[] = '<div class="navigate-form-row">';
			$field[] = '<label>'.$property->name.'</label>';		
			$field[] = $naviforms->textfield("property-".$property->id.'-latitude',  $latitude, '182px');
			$field[] = $naviforms->textfield("property-".$property->id.'-longitude', $longitude, '182px');
			$field[] = '<img src="img/icons/silk/map_magnify.png" align="absmiddle" hspace="3px" id="property-'.$property->id.'-show" />';
			$field[] = '<div id="property-'.$property->id.'-map-container" style=" display: none; ">';
			$field[] = '	<div class="navigate-form-row" id="property-'.$property->id.'-search" style=" width: 320px; height: 24px; margin-top: 5px; margin-left: 85px; position: absolute; z-index: 100; opacity: 0.9; ">';
			$field[] = '		<input type="text" name="property-'.$property->id.'-search-text" style=" width: 280px; " /> ';
			$field[] = '		<img class="ui-widget ui-button ui-state-default ui-corner-all" sprite="false" style=" cursor: pointer; padding: 3px; " src="'.NAVIGATE_URL.'/img/icons/silk/zoom.png" align="right" />';			
			$field[] = '	</div>';
			$field[] = '	<div id="property-'.$property->id.'-map" style=" width: 400px; height: 200px; "></div>';
			$field[] = '</div>';		
			$field[] = '</div>';		
			$field[] = '<script language="javascript" type="text/javascript" src="https://maps.google.com/maps/api/js?sensor=false"></script>';

			$layout->add_script('
				var property_'.$property->id.'_gmap = null;
			    var marker = null;
							
				$("#property-'.$property->id.'-search input").bind("keyup", function(e)
				{	if(e.keyCode == 13)	property'.$property->id.'search();	});
				
				$("#property-'.$property->id.'-search img").bind("click", property'.$property->id.'search);		
				
				$("#property-'.$property->id.'-show").bind("click", function()
				{
					var myLatlng = new google.maps.LatLng(
					    $("#property-'.$property->id.'-latitude").val(),
					    $("#property-'.$property->id.'-longitude").val()
					);
					var myOptions = {
						zoom: 16,
						center: myLatlng,
						mapTypeId: google.maps.MapTypeId.ROADMAP,
						disableDoubleClickZoom: true
					};					
					property_'.$property->id.'_gmap = new google.maps.Map($("#property-'.$property->id.'-map")[0], myOptions);
					var marker = new google.maps.Marker({
						  position: myLatlng,
						  title: myLatlng.lat() + ", " + myLatlng.lng()
					});
					marker.setMap(property_'.$property->id.'_gmap);  
					google.maps.event.addListener(property_'.$property->id.'_gmap, "dblclick", function(event) 
					{
						marker.setMap(null);

						$("#property-'.$property->id.'-latitude").val(event.latLng.lat());
						$("#property-'.$property->id.'-longitude").val(event.latLng.lng())
						marker = new google.maps.Marker({
						  position: event.latLng,
						  title: event.latLng.lat() + ", " + event.latLng.lng()
						});
						marker.setMap(property_'.$property->id.'_gmap);
					});

					$("#property-'.$property->id.'-map-container").dialog({
						width: 600,
						height: 400,
						title: "'.t(300, 'Map').': '.t(301, 'Double click a place to set the coordinates').'",
						resize: property'.$property->id.'resize
					}).dialogExtend(
					{
						maximize: true,
						minimize: false,
						"events" : { "maximize" : property'.$property->id.'resize,
									 "restore" : property'.$property->id.'resize }
					});
					
					property'.$property->id.'resize();

				}).css("cursor", "pointer");	
				
				function property'.$property->id.'resize()
				{
					$("#property-'.$property->id.'-map").width($("#property-'.$property->id.'-map-container").width()); 
					$("#property-'.$property->id.'-map").height($("#property-'.$property->id.'-map-container").height());	
					google.maps.event.trigger(property_'.$property->id.'_gmap, "resize");
				}
				
				function property'.$property->id.'search()
				{				
					var geocoder = new google.maps.Geocoder();
					geocoder.geocode( { "address": $("#property-'.$property->id.'-search input").val()}, function(results, status) 
					{
						if (status == google.maps.GeocoderStatus.OK)
						{
							property_'.$property->id.'_gmap.setCenter(results[0].geometry.location);
							var marker = new google.maps.Marker(
							{
								map: property_'.$property->id.'_gmap, 
								position: results[0].geometry.location
							});
						} 
						else 
						{
							alert("Geocode was not successful for the following reason: " + status);
						}
					});
					
					return false;
				}				
			');
			break;		
			
		case 'text':
			foreach($langs as $lang)
			{
				if(!is_array($property->value))
				{
					$ovalue = $property->value;
					$property->value = array();
					foreach($langs as $lang_value)
						$property->value[$lang_value] = $ovalue;
				}

                $language_info = '<span class="navigate-form-row-language-info" title="'.language::name_by_code($lang).'"><img src="img/icons/silk/comment.png" align="absmiddle" />'.$lang.'</span>';

				$field[] = '<div class="navigate-form-row" lang="'.$lang.'">';
				$field[] = '<label>'.$property->name.' '.$language_info.'</label>';
				$field[] = $naviforms->textfield("property-".$property->id."-".$lang, $property->value[$lang]);
				$field[] = '</div>';						
			}
			break;
			
		case 'textarea':
			foreach($langs as $lang)
			{
				if(!is_array($property->value))
				{
					$ovalue = $property->value;
					$property->value = array();
					foreach($langs as $lang_value)
						$property->value[$lang_value] = $ovalue;
				}

                $language_info = '<span class="navigate-form-row-language-info" title="'.language::name_by_code($lang).'"><img src="img/icons/silk/comment.png" align="absmiddle" />'.$lang.'</span>';

               	$style = "";
                if(!empty($property->width))
                	$style = ' width: '.$property->width.'px; ';

				$field[] = '<div class="navigate-form-row" lang="'.$lang.'">';
				$field[] = '<label>'.$property->name.' '.$language_info.'</label>';
				$field[] = $naviforms->textarea("property-".$property->id."-".$lang, $property->value[$lang], 4, 48, $style);
				$field[] = '</div>';			
			}		
			break;

        case 'rich_textarea':
            foreach($langs as $lang)
            {
                if(!is_array($property->value))
                {
                    $ovalue = $property->value;
                    $property->value = array();
                    foreach($langs as $lang_value)
                        $property->value[$lang_value] = $ovalue;
                }

                $language_info = '<span class="navigate-form-row-language-info" title="'.language::name_by_code($lang).'"><img src="img/icons/silk/comment.png" align="absmiddle" />'.$lang.'</span>';

                $style = "";
                if(!empty($property->width))
                    $style = ' width: '.$property->width.'px; ';

                $field[] = '<div class="navigate-form-row" lang="'.$lang.'">';
                $field[] = '<label>'.$property->name.' '.$language_info.'</label>';
                $field[] = $naviforms->editorfield("property-".$property->id."-".$lang, $property->value[$lang]);
                $field[] = '</div>';
            }
            break;

        case 'color':
            $field[] = '<div class="navigate-form-row">';
            $field[] = '<label>'.$property->name.'</label>';
            $field[] = $naviforms->colorfield("property-".$property->id, $property->value);
            $field[] = '</div>';
            break;

		case 'date':
			$field[] = '<div class="navigate-form-row">';
			$field[] = '<label>'.$property->name.'</label>';		
			$field[] = $naviforms->datefield("property-".$property->id, $property->value, false);
			$field[] = '</div>';			
			break;
			
		case 'datetime':
			$field[] = '<div class="navigate-form-row">';
			$field[] = '<label>'.$property->name.'</label>';		
			$field[] = $naviforms->datefield("property-".$property->id, $property->value, true);
			$field[] = '</div>';					
			break;

        case 'source_code':
            $field[] = '<div class="navigate-form-row">';
            $field[] = '<label>'.$property->name.'</label>';
            $field[] = $naviforms->scriptarea("property-".$property->id, $property->value);
            $field[] = '</div>';
            break;
		
		case 'link':
			foreach($langs as $lang)
			{
				if(!is_array($property->value))
				{
					$ovalue = $property->value;
					$property->value = array();
					foreach($langs as $lang_value)
						$property->value[$lang_value] = $ovalue;
				}

                $link = explode('##', $property->value[$lang]);
                if(is_array($link))
                {
                    $title = $link[1];
                    $link = $link[0];
                    if(empty($title))
                        $title = $link;
                }
                else
                {
                    $title = $property->value[$lang];
                    $link = $property->value[$lang];
                }

                $language_info = '<span class="navigate-form-row-language-info" title="'.language::name_by_code($lang).'"><img src="img/icons/silk/comment.png" align="absmiddle" />'.$lang.'</span>';

				$field[] = '<div class="navigate-form-row" lang="'.$lang.'">';
				$field[] = '<label>'.$property->name.' '.$language_info.'</label>';
                $field[] = $naviforms->textfield("property-".$property->id."-".$lang."-title", $title);
                $field[] = '<span class="navigate-form-row-info">'.t(67, 'Title').'</span>';
                $field[] = '</div>';
                $field[] = '<div class="navigate-form-row" lang="'.$lang.'">';
                $field[] = '<label>&nbsp;</label>';
                $field[] = $naviforms->textfield("property-".$property->id."-".$lang."-link", $link);
                $field[] = '<span class="navigate-form-row-info">'.t(197, 'Enlace').'</span>';
                $field[] = '</div>';
			}		
			break;
			
		case 'image':
			$field[] = '<div class="navigate-form-row">';
			$field[] = '<label>'.$property->name.'</label>';		
			$field[] = $naviforms->dropbox("property-".$property->id, $property->value, "image");
			$field[] = '</div>';				
			break;

        case 'video':
            $field[] = '<div class="navigate-form-row">';
            $field[] = '<label>'.$property->name.'</label>';
            $field[] = $naviforms->dropbox("property-".$property->id, $property->value, "video");
            $field[] = '</div>';
            break;

		case 'file':
			$field[] = '<div class="navigate-form-row">';
			$field[] = '<label>'.$property->name.'</label>';		
			$field[] = $naviforms->dropbox("property-".$property->id, $property->value);
			$field[] = '</div>';						
			break;
			
		case 'comment':
			$field[] = '<div class="navigate-form-row">';
			$field[] = '<label>'.$property->name.'</label>';		
			$field[] = '<div class="subcomment">'.$property->value.'</div>';
			$field[] = '</div>';								
			break;
			
		case 'category':
            $hierarchy = structure::hierarchy(0);
            $categories_list = structure::hierarchyList($hierarchy, $property->value);

            if(empty($categories_list))
                $categories_list = '<ul><li value="0">'.t(428, '(no category)').'</li></ul>';

            $field[] = '<div class="navigate-form-row">';
            $field[] = '<label>'.$property->name.'</label>';
            $field[] = $naviforms->dropdown_tree("property-".$property->id, $categories_list, $property->value);
            $field[] = '</div>';
            break;

        case 'webuser_groups':
            $webuser_groups = webuser_group::all_in_array();

            // to get the array of groups first we remove the "g" character
            $property->value    = str_replace('g', '', $property->value);
            $property->value    = explode(',', $property->value);

            $field[] = '<div class="navigate-form-row">';
            $field[] = '<label>'.$property->name.'</label>';
            $field[] = $naviforms->multiselect(
                'property-'.$property->id,
                array_keys($webuser_groups),
                array_values($webuser_groups),
                $property->value
            );
            $field[] = '</div>';
            break;

        case 'product':
            // TO DO (when navigate has products!)
            break;
			
		default:
	}
	
	return implode("\n", $field);
}

?>