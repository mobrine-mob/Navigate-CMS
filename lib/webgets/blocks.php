<?php

function nvweb_blocks($vars=array())
{
	global $website;
	global $DB;
	global $current;
    global $webgets;

    $webget = 'blocks';

	$out = '';

    $access = array();
    $access[] = 0;
    if(empty($current['webuser'])) // 1: only signed in users, 2: only NON signed in users
        $access[] = 2;
    else
    {
        $access[] = 1;
        $access[] = 3;
    }

    // blocks type cache
    if(empty($webgets[$webget]['block_types']))
        $webgets[$webget]['block_types'] = block::types();

	$block_types = $webgets[$webget]['block_types'];

    // get the index for the block type requested
    $bti = array_multidimensional_search($block_types, array('code' => $vars['type']));

    // how must we process this block type
	$order_mode = @$block_types[$bti]['order'];
	if(empty($order_mode) || $order_mode=='theme')
    	$order_mode = @$vars['mode'];

    // how many blocks of this type we have to show
    $howmany = @intval($block_types[$bti]['maximum']);
    if(empty($howmany) || ($howmany > intval(@$vars['number']) && !empty($vars['number'])))
		$howmany = intval(@$vars['number']) + 0;

    // which zone of the block we have to return
    if(empty($vars['zone']))
        $vars['zone'] = 'block';

    $blocks = array();

	switch($order_mode)
	{
        case 'single':
            $DB->query('SELECT id
                          FROM nv_blocks
                         WHERE type = '.protect($vars['type']).'
                           AND enabled = 1
                           AND website = '.$website->id.'
                           AND (date_published = 0 OR date_published < '.core_time().')
                           AND (date_unpublish = 0 OR date_unpublish > '.core_time().')
                           AND access IN('.implode(',', $access).')
                           AND (categories = "" OR FIND_IN_SET("'.$current['category'].'", categories) > 0)
                           AND id = '.protect($vars['id'])
            );
            $row = $DB->first();
            if(!empty($row))
                $blocks[] = $row['id'];
            break;

        case 'priority':
		case 'ordered':
			$DB->query('SELECT *
			 			  FROM nv_blocks
						 WHERE type = '.protect($vars['type']).'
						   AND enabled = 1
						   AND website = '.$website->id.'
                           AND (date_published = 0 OR date_published < '.core_time().')
                           AND (date_unpublish = 0 OR date_unpublish > '.core_time().')
                           AND access IN('.implode(',', $access).')
                           AND (categories = "" OR FIND_IN_SET("'.$current['category'].'", categories) > 0)
					  ORDER BY position ASC');
		
			$rows = $DB->result();

			foreach($rows as $row)
			{				
				if(!nvweb_block_enabled($row)) continue;
                $row = (array)$row;
                $blocks[] = $row['id'];
			}
			break;
		
		default:			
		case 'random':
            // "random" gets priority blocks first
            // retrieve fixed blocks
            $DB->query('SELECT *
			 			  FROM nv_blocks
						 WHERE type = '.protect($vars['type']).'
						   AND enabled = 1
						   AND website = '.$website->id.'
                           AND (date_published = 0 OR date_published < '.core_time().')
                           AND (date_unpublish = 0 OR date_unpublish > '.core_time().')
                           AND access IN('.implode(',', $access).')
                           AND fixed = 1
                           AND (categories = "" OR FIND_IN_SET("'.$current['category'].'", categories) > 0)
					  ORDER BY position ASC');

			$fixed_rows = $DB->result();
			$fixed_rows_ids = $DB->result('id');

        	if(empty($fixed_rows_ids))
        		$fixed_rows_ids = array(0);

            // now retrieve the other blocks in random order
			$DB->query('SELECT *
			 			  FROM nv_blocks
						 WHERE type = '.protect($vars['type']).'
						   AND enabled = 1
						   AND website = '.$website->id.'
                           AND (date_published = 0 OR date_published < '.core_time().')
                           AND (date_unpublish = 0 OR date_unpublish > '.core_time().')
                           AND access IN('.implode(',', $access).')
                           AND id NOT IN('.implode(",", $fixed_rows_ids).')
                           AND (categories = "" OR FIND_IN_SET("'.$current['category'].'", categories) > 0)
						 ORDER BY RAND()');
		
			$random_rows = $DB->result();

			// mix rows (fixed and random)

			// fixed position rows
    		foreach($fixed_rows as $fr)
			{
				if(!nvweb_block_enabled($fr)) continue;
                $blocks[$fr->position] = $fr->id;
			}

			// random position rows
			$pos = 0;
			foreach($random_rows as $rr)
			{
				if(!nvweb_block_enabled($rr)) continue;
				
				// find next free position
				$free = false;
				while(!$free)
				{
					$free = empty($blocks[$pos]);
					if(!$free)
						$pos++;
				}
                $blocks[$pos] = $rr->id;
			}
			
            // sort array by key
            ksort($blocks);
			break;			
	}

    // render the blocks found
    $shown = 0;
    $position = 1;
    foreach($blocks as $id)
    {
        if($howmany > 0 && $shown >= $howmany) break;

        $block = new block();
        $block->load($id);

        $bt = 'block';
        for($bti=0; $bti < count($block_types); $bti++)
        {
            if($block->type == $block_types[$bti]['code'])
            {
                $bt = $block_types[$bti]['type'];
                break;
            }
        }

        // SKIP all blocks to a certain position?
        if(isset($vars['position']) && $vars['position']!=$position)
        {
            $position++;
            continue;
        }

        // RENDER block zone
        switch($vars['zone'])
        {
            case 'object':
                $block_objects[] = $block;
                break;

            case 'title':
                $out.= '<span class="block-'.$vars['type'].'-title" zone="title" ng-block-id="'.$block->id.'">'.$block->dictionary[$current['lang']]['title'].'</span>';
                break;

            case 'content':
            case 'block':
            default:
                if($bt=='theme')
                {
                    $fn = 'nvweb_'.$website->theme.'_blocks_render';

                    $out.= '<div class="block-'.$vars['type'].'" ng-block-id="'.$block->id.'">';
                    $out.= $fn($block, $vars);
                    $out.= '</div>'."\n";
                }
                else
                {
                    $out.= '<div class="block-'.$vars['type'].'" ng-block-id="'.$block->id.'">';
                    $out.= nvweb_blocks_render($vars['type'], $block->trigger, $block->action, $vars['zone']);
                    $out.= '</div>'."\n";
                }
                break;
        }

        // the block requested at a CERTAIN POSITION was this, we've finished
        if(isset($vars['position']) && $vars['position']==$position)
            break;

        $shown++;
        $position++;
    }

    if($vars['zone']=='object')
    {
        $out = array($block_objects, count($blocks));
    }

	return $out;
}

function nvweb_blocks_render($type, $trigger, $action, $zone)
{
	global $current;
	global $website;
    global $theme;

    $block_types = array_merge((array)$website->block_types, (array)$theme->blocks);

	foreach($block_types as $btype)
	{
        $btype = (array)$btype;

		if($btype['title']==$type || $btype['code']==$type)
            $type  = $btype;
	}
	
	$lang = $current['lang'];
	
	$sizes = '';
	
	if(!empty($type['width'])) $sizes.= ' width="'.$type['width'].'" ';
	if(!empty($type['height'])) $sizes.= ' height="'.$type['height'].'" ';

	switch($trigger['trigger-type'][$lang])
	{
		case 'image':
			$trigger_html = '<img src="'.NVWEB_ABSOLUTE.'/object?type=image&id='.$trigger['trigger-image'][$lang].'&width='.$type['width'].'&height='.$type['height'].'" '.$sizes.' />';
			break;
			
		case 'rollover':
			$trigger_html = '<img src="'.NVWEB_ABSOLUTE.'/object?type=image&id='.$trigger['trigger-rollover'][$lang].'&width='.$type['width'].'&height='.$type['height'].'" 
								   '.$sizes.'
								  onmouseover="this.src=\''.NVWEB_ABSOLUTE.'/object?type=image&id='.$trigger['trigger-rollover']['active-'.$lang].'&width='.$type['width'].'&height='.$type['height'].'\';" 
								  onmouseout="this.src=\''.NVWEB_ABSOLUTE.'/object?type=image&id='.$trigger['trigger-rollover'][$lang].'&width='.$type['width'].'&height='.$type['height'].'\';" />';
			break;
			
		case 'flash':
			$clickTAG = '&clickTAG='.urlencode($action['action-web'][$lang]);
		
			$trigger_html = '<!--[if !IE]> -->
								<object type="application/x-shockwave-flash"
							  			data="'.NVWEB_ABSOLUTE.'/object?type=file&id='.$trigger['trigger-flash'][$lang].'&disposition=inline'.$clickTAG.'" '.$sizes.'>
							<!-- <![endif]-->
							<!--[if IE]>
								<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"
									  	codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,0,0"  '.$sizes.'>
									  		<param name="movie" value="'.NVWEB_ABSOLUTE.'/object?type=file&id='.$trigger['trigger-flash'][$lang].'&disposition=inline'.$clickTAG.'" />
							<!-->
							<!--dgx-->
											<param name="menu" value="false" />
											<p>Flash is not installed.</p>
								</object>
							<!-- <![endif]-->
					';		
					
			// disable any action, flash do not allow being wrapped by <a>
			$action = array();
			break;
			
		case 'html':
			$trigger_html = htmlspecialchars_decode($trigger['trigger-html'][$lang]);
			break;

        case 'content':
            $trigger_html = htmlspecialchars_decode($trigger['trigger-content'][$lang]);
            $trigger_html = str_replace('\"', '', $trigger_html);
            break;

		default:
		case '':	// hidden
			break;
	}

    if($zone=='content' || $zone=='trigger')
        return $trigger_html;

    $action = nvweb_blocks_render_action($action, $trigger_html, $lang);

	return $action;
	
}

function nvweb_blocks_render_action($action, $trigger_html, $lang, $return_url=false)
{
	switch(@$action['action-type'][$lang])
    {
        case 'web':
            $url = $action['action-web'][$lang];
            $action = '<a href="'.$url.'">'.$trigger_html.'</a>';
            break;

        case 'web-n':
            $url = $action['action-web'][$lang];
            $action = '<a href="'.$url.'" target="_blank">'.$trigger_html.'</a>';
            break;

        case 'file':
            $url = NVWEB_ABSOLUTE.'/object?type=file&id='.$action['action-file'][$lang];
            $action = '<a href="'.$url.'">'.$trigger_html.'</a>';
            break;

        case 'image':
            $url = NVWEB_ABSOLUTE.'/object?type=image&id='.$action['action-file'][$lang];
            $action = '<a href="'.$url.'">'.$trigger_html.'</a>';
            break;

        default:
        case '':	// do nothing
            $action = $trigger_html;
            break;
    }

    if($return_url)
        return $url;

    return $action;
}

function nvweb_block_enabled($object)
{
	$enabled = ($object->enabled=='1');
	$enabled = $enabled && (empty($object->date_published) || ($object->date_published < core_time()));
	$enabled = $enabled && (empty($object->date_unpublish) || ($object->date_unpublish > core_time()));

	// check access
	if(isset($object->access))
	{
		$access = true;
		
		switch($object->access)
		{
			case 2:	// accessible to NOT SIGNED IN visitors
				$access = empty($current['webuser']);
				break;
			
			case 1: // accessible to WEB USERS ONLY
				$access = !empty($current['webuser']);
				break;
			
			case 0:	// accessible to EVERYBODY 
			default:
				$access = true;
		}
		
		$enabled = $enabled && $access;
	}
	
	return $enabled;
}

?>