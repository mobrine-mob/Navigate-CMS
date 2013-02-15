<?php
require_once(NAVIGATE_PATH.'/lib/packages/webusers/webuser.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/webusers/webuser_group.class.php');
require_once(NAVIGATE_PATH.'/lib/packages/properties/property.class.php');

function run()
{
	global $user;	
	global $layout;
	global $DB;
	global $website;
	
	$out = '';
	$item = new webuser();
			
	switch($_REQUEST['act'])
	{
		case 1:	// json data retrieval & operations
			switch($_REQUEST['oper'])
			{
				case 'del':	// remove rows
					$ids = $_REQUEST['ids'];
					foreach($ids as $id)
					{
						$item->load($id);
						$item->delete();
					}
					echo json_encode(true);
					break;
					
				default: // list or search	
					$page = intval($_REQUEST['page']);
					$max	= intval($_REQUEST['rows']);
					$offset = ($page - 1) * $max;
					$orderby= $_REQUEST['sidx'].' '.$_REQUEST['sord'];
					$where = ' website = '.$website->id;
										
					if($_REQUEST['_search']=='true' || isset($_REQUEST['quicksearch']))
					{
						if(isset($_REQUEST['quicksearch']))
							$where .= $item->quicksearch($_REQUEST['quicksearch']);
						else if(isset($_REQUEST['filters']))
							$where .= navitable::jqgridsearch($_REQUEST['filters']);
						else	// single search
							$where .= ' AND '.navitable::jqgridcompare($_REQUEST['searchField'], $_REQUEST['searchOper'], $_REQUEST['searchString']);
					}
				
					$DB->queryLimit('id,avatar,username,fullname,joindate,blocked', 
									'nv_webusers', 
									$where, 
									$orderby, 
									$offset, 
									$max);
									
					$dataset = $DB->result();
					$total = $DB->foundRows();
					
					//echo $DB->get_last_error();
					
					$out = array();					
											
					for($i=0; $i < count($dataset); $i++)
					{							
						$out[$i] = array(
							0	=> $dataset[$i]['id'],
							1	=> empty($dataset[$i]['avatar'])? '' : '<img title="'.$f->name.'" src="'.NAVIGATE_DOWNLOAD.'?wid='.$website->id.'&id='.urlencode($dataset[$i]['avatar']).'&amp;disposition=inline&amp;width=32&amp;height=32" />',
							2	=> $dataset[$i]['username'],
							3	=> $dataset[$i]['fullname'],
							4	=> $dataset[$i]['groups'],
							5 	=> core_ts2date($dataset[$i]['joindate'], true),
							6	=> (($dataset[$i]['blocked']==0)? '<img src="img/icons/silk/accept.png" />' : '<img src="img/icons/silk/cancel.png" />')
						);
					}
									
					navitable::jqgridJson($out, $page, $offset, $max, $total);					
					break;
			}
			
			session_write_close();
			exit;
			break;

		case 2: // edit/new form
        case 'create':
			if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));	
			}
		
			if(isset($_REQUEST['form-sent']))
			{
				$item->load_from_post();
				try
				{
					$item->save();
					$layout->navigate_notification(t(53, "Data saved successfully."), false);	
				}
				catch(Exception $e)
				{
					$layout->navigate_notification($e->getMessage(), true, true);	
				}
			}
		
			$out = webusers_form($item);
			break;
					
		case 4: // remove 
			if(!empty($_REQUEST['id']))
			{
				$item->load(intval($_REQUEST['id']));	
				if($item->delete() > 0)
				{
					$layout->navigate_notification(t(55, 'Item removed successfully.'), false);
					$out = webusers_list();
				}
				else
				{
					$layout->navigate_notification(t(56, 'Unexpected error.'), false);
					$out = webusers_form($item);
				}
			}
			break;
			
		case 90: // json request: timezones by country
			$timezones = property::timezones($_REQUEST['country']);			
			
			if(empty($timezones))
				$timezones = property::timezones();				
			
			echo json_encode($timezones);			
			core_terminate();
			break;

        case 'export':
            // export web users list to a CSV file
            webuser::export();
            break;

        case 'webuser_groups_list':
            $out = webuser_groups_list();
            break;

        case 'webuser_groups_json':
            $page   = intval($_REQUEST['page']);
            $max	= intval($_REQUEST['rows']);
            $offset = ($page - 1) * $max;

            $rs = webuser_group::all($_REQUEST['sidx'], $_REQUEST['sord']);

            $dataset = array();

            foreach($rs as $row)
            {
                $dataset[] = array(
                    'id' => $row->id,
                    'name' => $row->name
                );
            }

            $total = count($dataset);
            navitable::jqgridJson($dataset, $page, $offset, $max, $total, 'id');

            session_write_close();
            exit;
            break;

        case 'webuser_group_edit':
            $webuser_group = new webuser_group();

            if(!empty($_REQUEST['id']))
                $webuser_group->load(intval($_REQUEST['id']));

            if(isset($_REQUEST['form-sent']))
            {
                $webuser_group->load_from_post();

                try
                {
                    $ok = $webuser_group->save();
                    $layout->navigate_notification(t(53, "Data saved successfully."), false);
                }
                catch(Exception $e)
                {
                    $layout->navigate_notification($e->getMessage(), true, true);
                }
            }

            $out = webuser_groups_form($webuser_group);
            break;

        case 'webuser_group_delete':
            $webuser_group = new webuser_group();

            if(!empty($_REQUEST['id']))
                $webuser_group->load(intval($_REQUEST['id']));

            try
            {
                $webuser_group->delete();
                $layout->navigate_notification(t(55, 'Item removed successfully.'), false);
                $out = webuser_groups_list();
            }
            catch(Exception $e)
            {
                $out = $layout->navigate_message("error", t(24, 'Web users').' / '.t(506, 'Groups'), t(56, 'Unexpected error.'));
            }
            break;
					
		case 0: // list / search result
        case 'list':
		default:			
			$out = webusers_list();
			break;
	}
	
	return $out;
}

function webusers_list()
{
	$navibars = new navibars();
	$navitable = new navitable("webusers_list");
	
	$navibars->title(t(24, 'Web users'));

    $navibars->add_actions(
        array(
            '<a href="?fid='.$_REQUEST['fid'].'&act=export"><img height="16" align="absmiddle" width="16" src="img/icons/silk/table_save.png"> '.t(475, 'Export').'</a>'
        )
    );

    $navibars->add_actions(		array(	'<a href="?fid='.$_REQUEST['fid'].'&act=webuser_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/group.png"> '.t(506, 'Groups').'</a>'	));

    $navibars->add_actions(
        array(
            '<a href="?fid='.$_REQUEST['fid'].'&act=create"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
            '<a href="?fid='.$_REQUEST['fid'].'&act=list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
            'search_form'
        )
    );
	
	if($_REQUEST['quicksearch']=='true')
		$navitable->setInitialURL("?fid=".$_REQUEST['fid'].'&act=1&_search=true&quicksearch='.$_REQUEST['navigate-quicksearch']);
	
	$navitable->setURL('?fid='.$_REQUEST['fid'].'&act=1');
	$navitable->sortBy('id');
	$navitable->setDataIndex('id');
	$navitable->setEditUrl('id', '?fid='.$_REQUEST['fid'].'&act=2&id=');
    $navitable->enableDelete();
	
	$navitable->addCol("ID", 'id', "40", "true", "left");
	$navitable->addCol(t(246, 'Avatar'), 'avatar', "60", "true", "center");
	$navitable->addCol(t(1, 'User'), 'username', "100", "true", "left");		
	$navitable->addCol(t(159, 'Name'), 'fullname', "150", "true", "left");
	$navitable->addCol(t(506, 'Groups'), 'groups', "120", "true", "left");
	$navitable->addCol(t(247, 'Date joined'), 'joindate', "60", "true", "left");
	$navitable->addCol(t(321, 'Allowed'), 'blocked', "80", "true", "center");		

	$navibars->add_content($navitable->generate());	
	
	return $navibars->generate();
	
}

function webusers_form($item)
{
	global $user;
	global $DB;
	global $website;
	global $layout;
	
	$navibars = new navibars();
	$naviforms = new naviforms();
	$layout->navigate_media_browser();	// we can use media browser in this function
	
	if(empty($item->id))
		$navibars->title(t(24, 'Web users').' / '.t(38, 'Create'));	
	else
		$navibars->title(t(24, 'Web users').' / '.t(170, 'Edit').' ['.$item->id.']');		

	$navibars->add_actions(		array(	'<a href="#" onclick="javascript: navigate_media_browser();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/images.png"> '.t(36, 'Media').'</a>'	));
	$navibars->add_actions(		array(	'<a href="?fid='.$_REQUEST['fid'].'&act=webuser_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/group.png"> '.t(506, 'Groups').'</a>'	));

	if(empty($item->id))
	{
		$navibars->add_actions(		array(	'<a href="#" onclick="navigate_tabform_submit(1);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>'	)
									);
	}
	else
	{
		$navibars->add_actions(		array(	'<a href="#" onclick="navigate_tabform_submit(1);"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>',
											'<a href="#" onclick="navigate_delete_dialog();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/cancel.png"> '.t(35, 'Delete').'</a>'
										)
									);		
		
		$delete_html = array();
		$delete_html[] = '<div id="navigate-delete-dialog" class="hidden">'.t(57, 'Do you really want to delete this item?').'</div>';
		$delete_html[] = '<script language="javascript" type="text/javascript">';
		$delete_html[] = 'function navigate_delete_dialog()';		
		$delete_html[] = '{';				
		$delete_html[] = '$("#navigate-delete-dialog").dialog({
							resizable: true,
							height: 150,
							width: 300,
							modal: true,
							title: "'.t(59, 'Confirmation').'",
							buttons: {
								"'.t(35, 'Delete').'": function() {
									$(this).dialog("close");
									window.location.href = "?fid='.$_REQUEST['fid'].'&act=4&id='.$item->id.'";
								},
								"'.t(58, 'Cancel').'": function() {
									$(this).dialog("close");
								}
							}
						});';		
		$delete_html[] = '}';							
		$delete_html[] = '</script>';						
									
		$navibars->add_content(implode("\n", $delete_html));
	}
	
	$navibars->add_actions(	array(	(!empty($item->id)? '<a href="?fid=webusers&act=2"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
									'<a href="?fid=webusers&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>',
									'search_form' ));

	$navibars->form();

	$navibars->add_tab(t(43, "Main"));
	
	$navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
	$navibars->add_tab_content($naviforms->hidden('id', $item->id));	
	
	$navibars->add_tab_content_row(array(	'<label>ID</label>',
											'<span>'.(!empty($item->id)? $item->id : t(52, '(new)')).'</span>' ));


	$navibars->add_tab_content_row(array(	'<label>'.t(246, 'Avatar').'</label>',
											$naviforms->dropbox('webuser-avatar', $item->avatar, "image"),
										));																				

	$navibars->add_tab_content_row(array(	'<label>'.t(1, 'User').'</label>',
											$naviforms->textfield('webuser-username', $item->username),
										));																	

	$navibars->add_tab_content_row(array(	'<label>'.t(2, 'Password').'</label>',
											'<input type="password" name="webuser-password" value="" size="32" />',
											'<span class="navigate-form-row-info">'.t(48, "Leave blank to keep the current value").'</span>' ));
											
	$navibars->add_tab_content_row(array(	'<label>'.t(44, 'E-Mail').'</label>',
											'<input type="text" name="webuser-email" value="'.$item->email.'" size="64" />' ));

	if(!empty($item->joindate))
	{
		$navibars->add_tab_content_row(array(	'<label>'.t(247, 'Date joined').'</label>',
												core_ts2date($item->joindate, true),
											));												
	}
	
	$navibars->add_tab_content_row(array(	'<label>'.t(249, 'Newsletter').'</label>',
											$naviforms->checkbox('webuser-newsletter', $item->newsletter),
										));	
										
	$navibars->add_tab_content_row(array(	'<label>'.t(47, 'Blocked').'</label>',
											$naviforms->checkbox('webuser-blocked', $item->blocked),
										));


    $navibars->add_tab(t(506, "Groups"));

    $webuser_groups = webuser_group::all_in_array();

    $navibars->add_tab_content_row(
        array(  '<label>'.t(506, "Groups").'</label>',
                $naviforms->multiselect(
                    'webuser-groups',
                    array_keys($webuser_groups),
                    array_values($webuser_groups),
                    $item->groups
                )
        )
    );

	$navibars->add_tab(t(308, "Personal"));
											
	$navibars->add_tab_content_row(
        array(
            '<label>'.t(159, 'Name').'</label>',
            $naviforms->textfield('webuser-fullname', $item->fullname)
        )
    );
										
	$navibars->add_tab_content_row(
        array(
            '<label>'.t(304, 'Gender').'</label>',
            $naviforms->buttonset(
                'webuser-gender',
                array(
                    'male' => '<img src="img/icons/silk/male.png" align="absbottom" /> '.t(305, 'Male'),
                    'female' => '<img src="img/icons/silk/female.png" align="absbottom" /> '.t(306, 'Female'),
                    '' => '<img src="img/icons/silk/help.png" align="absbottom" /> '.t(307, 'Unspecified')
                ),
                $item->gender
            )
        )
    );

	$navibars->add_tab_content_row(array(	'<label>'.t(248, 'Birthdate').'</label>',
											$naviforms->datefield('webuser-birthdate', $item->birthdate, false),
										));		

	$countries = property::countries();
	
	$navibars->add_tab_content_row(array(	'<label>'.t(224, 'Country').'</label>',
											$naviforms->selectfield("webuser-country", array_keys($countries), array_values($countries), strtoupper($item->country))
										));		
										
	$timezones = property::timezones();
	
	if(empty($item->timezone))
		$item->timezone = date_default_timezone_get();	

	$navibars->add_tab_content_row(array(	'<label>'.t(97, 'Timezone').'</label>',
											$naviforms->selectfield("webuser-timezone", array_keys($timezones), array_values($timezones), $item->timezone)
										));												
										
	$layout->add_script('
		var webuser_country = "'.$item->country.'";
		$("#webuser-country").bind("change blur", function()
		{
			if($(this).val() != webuser_country)
			{
				webuser_country = $(this).val();
				$.getJSON("?fid='.$_REQUEST['fid'].'", { country: $(this).val(), act: 90 }, function(data) 
				{
					$("#webuser-timezone").find("option").remove();
					
					$.each(data, function(value, text) 
					{
						$("<option />", {
							value: value,
							html: text
						}).appendTo("#webuser-timezone");
					});				
				});				
			}
		});
	');											

	// Language selector
	$data = language::language_names(false);
		
	$select = $naviforms->selectfield('webuser-language', array_keys($data), array_values($data), $item->language);
	$navibars->add_tab_content_row(array(	'<label>'.t(46, 'Language').'</label>',
											$select ));

	$navibars->add_tab(t(233, "Address"));
											
	$navibars->add_tab_content_row(array(	'<label>'.t(233, 'Address').'</label>',
											$naviforms->textfield('webuser-address', $item->address)		
										));	

	$navibars->add_tab_content_row(array(	'<label>'.t(319, 'Location').'</label>',
											$naviforms->textfield('webuser-location', $item->location)		
										));											

	$navibars->add_tab_content_row(array(	'<label>'.t(318, 'Zip code').'</label>',
											$naviforms->textfield('webuser-zipcode', $item->zipcode)		
										));											
										
	$navibars->add_tab_content_row(array(	'<label>'.t(320, 'Phone').'</label>',
											$naviforms->textfield('webuser-phone', $item->phone)		
										));											
											
	$navibars->add_tab(t(309, "Social"));
											
	$navibars->add_tab_content_row(array(	'<label>'.t(177, 'Website').'</label>',
											$naviforms->textfield('webuser-social_website', $item->social_website)											
										));

    /* webuser groups management */
    /*
    $table = new naviorderedtable("items_order_table");
    $table->setWidth("560px");
    $table->setHiddenInput("items-order");

    $table->addHeaderColumn('ID', 50);
    $table->addHeaderColumn(t(159, 'Name'), 450);

    $webuser_groups = webuser_group::all();
    foreach($webuser_groups as $group)
    {
        $table->addRow($group->id, array(
            array('content' => $group->id, 'align' => 'left'),
            array('content' => $group->name, 'align' => 'left')
        ));
    }

    $navibars->add_content('
        <div id="navigate_webuser_groups" style="display: none;">
            <div id="" class="navigate-form-row">
                <label style=" width: 100px; ">'.t(159, 'Group').'</label>
                <input type="text" style=" width: 300px;" value="" id="webuser_group-name" name="webuser_group-name" />
                <button>'.t(472, 'Add').'</button>
            </div>
            <div id="navigate_webuser_groups_list">
                '.$table->generate().'
            </div>
        </div>
    ');
    $layout->add_script('
        function navigate_webuser_groups()
        {
            $("#navigate_webuser_groups").dialog({
                title: "'.t(506, 'Groups').'",
                width: 600,
                height: 400,
                modal: true
            });
        }

        navigate_webuser_groups();
    ');
    */
											
	return $navibars->generate();
}

function webuser_groups_list()
{
    global $user;
    global $DB;
    global $website;

    $navibars = new navibars();
    $navitable = new navitable('webuser_groups_list');

    $navibars->title(t(24, 'Web users').' / '.t(506, 'Groups'));

    $navibars->add_actions(	array(	'<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/user.png"> '.t(24, 'Web users').'</a>' ) );

    $navibars->add_actions(
        array(
            '<a href="?fid='.$_REQUEST['fid'].'&act=webuser_group_edit"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>',
            '<a href="?fid='.$_REQUEST['fid'].'&act=webuser_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>'
        )
    );

    $navitable->setURL('?fid='.$_REQUEST['fid'].'&act=webuser_groups_json');
    $navitable->sortBy('id');
    $navitable->setDataIndex('id');
    $navitable->setEditUrl('id', '?fid='.$_REQUEST['fid'].'&act=webuser_group_edit&id=');

    $navitable->addCol("ID", 'id', "80", "true", "left");
    $navitable->addCol(t(159, 'Name'), 'name', "300", "true", "left");

    $navibars->add_content($navitable->generate());

    return $navibars->generate();
}

function webuser_groups_form($item)
{
    global $user;
    global $DB;
    global $website;
    global $layout;

    $navibars = new navibars();
    $naviforms = new naviforms();

    if(empty($item->id))
        $navibars->title(t(24, 'Web users').' / '.t(506, 'Groups').' / '.t(38, 'Create'));
    else
        $navibars->title(t(24, 'Web users').' / '.t(506, 'Groups').' / '.t(170, 'Edit').' ['.$item->id.']');

    if(empty($item->id))
    {
        $navibars->add_actions(
            array(
                '<a href="#" onclick="$(\'#navigate-content\').find(\'form\').eq(0).submit();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>'
            )
        );
    }
    else
    {
        $navibars->add_actions(
            array(
                '<a href="#" onclick="$(\'#navigate-content\').find(\'form\').eq(0).submit();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/accept.png"> '.t(34, 'Save').'</a>',
                '<a href="#" onclick="navigate_delete_dialog();"><img height="16" align="absmiddle" width="16" src="img/icons/silk/cancel.png"> '.t(35, 'Delete').'</a>'
            )
        );

        $delete_html = array();
        $delete_html[] = '<div id="navigate-delete-dialog" class="hidden">'.t(57, 'Do you really want to delete this item?').'</div>';
        $delete_html[] = '<script language="javascript" type="text/javascript">';
        $delete_html[] = 'function navigate_delete_dialog()';
        $delete_html[] = '{';
        $delete_html[] = '$("#navigate-delete-dialog").dialog({
                        resizable: true,
                        height: 150,
                        width: 300,
                        modal: true,
                        title: "'.t(59, 'Confirmation').'",
                        buttons: {
                            "'.t(58, 'Cancel').'": function() {
                                $(this).dialog("close");
                            },
                            "'.t(35, 'Delete').'": function() {
                                $(this).dialog("close");
                                window.location.href = "?fid='.$_REQUEST['fid'].'&act=webuser_group_delete&id='.$item->id.'";
                            }
                        }
                    });';
        $delete_html[] = '}';
        $delete_html[] = '</script>';

        $navibars->add_content(implode("\n", $delete_html));
    }

    $navibars->add_actions(	array(	'<a href="?fid='.$_REQUEST['fid'].'&act=0"><img height="16" align="absmiddle" width="16" src="img/icons/silk/user.png"> '.t(24, 'Web users').'</a>' ) );

    $navibars->add_actions(
        array(
            (!empty($item->id)? '<a href="?fid='.$_REQUEST['fid'].'&act=webuser_group_edit"><img height="16" align="absmiddle" width="16" src="img/icons/silk/add.png"> '.t(38, 'Create').'</a>' : ''),
            '<a href="?fid='.$_REQUEST['fid'].'&act=webuser_groups_list"><img height="16" align="absmiddle" width="16" src="img/icons/silk/application_view_list.png"> '.t(39, 'List').'</a>'
        )
    );

    $navibars->form();

    $navibars->add_tab(t(43, "Main"));

    $navibars->add_tab_content($naviforms->hidden('form-sent', 'true'));
    $navibars->add_tab_content($naviforms->hidden('id', $item->id));

    $navibars->add_tab_content_row(array(	'<label>ID</label>',
        '<span>'.(!empty($item->id)? $item->id : t(52, '(new)')).'</span>' ));

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(159, 'Name').'</label>',
            $naviforms->textfield('name', $item->name)
        )
    );

    $navibars->add_tab_content_row(
        array(
            '<label>'.t(334, 'Description').'</label>',
            $naviforms->textarea('description', $item->description)
        )
    );

    return $navibars->generate();
}
?>