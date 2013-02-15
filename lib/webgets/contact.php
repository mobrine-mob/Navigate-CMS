<?php
function nvweb_contact($vars=array())
{
	global $website;
	global $DB;
	global $current;
	global $webgets;
	global $dictionary;
	global $webuser;
	global $theme;
	
	$webget = 'contact';

	if(!isset($webgets[$webget]))
	{
		$webgets[$webget] = array();

		global $lang;		
		if(empty($lang))
		{		
			$lang = new language();
			$lang->load($current['lang']);
		}
		
		// default translations		
		$webgets[$webget]['translations'] = array(
				'name' => t(159, 'Name'),
				'email' => t(44, 'E-Mail'),
				'message' => t(380, 'Message'),
                'fields_blank' => t(444, 'You left some required fields blank.'),
                'contact_request_sent' => t(445, 'Your contact request has been sent. We will contact you shortly.'),
                'contact_request_failed' => t(446, 'We\'re sorry. Your contact request could not be sent. Please try again or find another way to contact us.')
		);
		
		// theme translations 
		// if the web theme has custom translations for this string subtypes, use it (for the user selected language)
		/* just add the following translations to your json theme dictionary:

			"name": "Name",
			"email": "E-Mail",
			"message": "Message",
		    "fields_blank": "You left some required fields blank.",
		    "contact_request_sent": "Your contact request has been sent. We will contact you shortly."

		*/
		if(!empty($website->theme) && function_exists($theme->t))
		{
			foreach($webgets[$webget]['translations'] as $code => $text)
			{
				$theme_translation = $theme->t($code);
				if(!empty($theme_translation))
					$webgets[$webget]['translations'][$code] = $theme_translation;
			}
		}
	}

	if(empty($vars['notify']))
        $vars['notify'] = 'alert';

	$out = '';	

	switch(@$vars['mode'])
	{	
		case 'send':
            if(!empty($_POST))  // form sent
            {
                // prepare fields and labels
                $fields = explode(',', @$vars['fields']);
                $labels = explode(',', @$vars['labels']);
                if(empty($labels))
                    $labels = $fields;

                $labels = array_map(
                    function($key)
                    {
                        global $webgets;
                        global $theme;

                        $tmp = $theme->t($key);
                        if(!empty($tmp))
                            return $theme->t($key);
                        else
                            return $webgets['contact']['translations'][$key];
                    },
                    $labels
                );
                $fields = array_combine($fields, $labels);

                // $fields = array( 'field_name' => 'field_label', ... )

                // check required fields
                $errors = array();
                $required = array();

                if(!empty($vars['required']))
                    $required = explode(',', $vars['required']);

                if(!empty($required))
                {
                    foreach($required as $field)
                    {
                        $value = trim($_POST[$field]);
                        if(empty($value))
                            $errors[] = $fields[$field];
                    }

                    if(!empty($errors))
                        return nvweb_contact_notify($vars, true, $webgets[$webget]['translations']['fields_blank'].' ('.implode(", ", $errors).')');
                }

                // create e-mail message and send it
                $message = nvweb_contact_generate($fields);

                $sent = nvweb_send_email($website->name, $message, $website->contact_emails);

                if($sent)
                    $out = nvweb_contact_notify($vars, false, $webgets[$webget]['translations']['contact_request_sent']);
                else
                    $out = nvweb_contact_notify($vars, true, $webgets[$webget]['translations']['contact_request_failed']);
            }

    }
	
	return $out;
}

function nvweb_contact_notify($vars, $is_error, $message)
{
    $out = '';

    switch($vars['notify'])
    {
        case 'inline':
            if($is_error)
                $out = '<div class="nvweb-contact-form-error">'.$message.'</div>';
            else
                $out = '<div class="nvweb-contact-form-success">'.$message.'</div>';
            break;

        case 'alert':
            nvweb_after_body('js', 'alert("'.$message.'");');
            break;

        default:
            nvweb_after_body('js', 'alert("'.$message.'");');
            break;
    }

    return $out;
}

function nvweb_contact_generate($fields)
{
    $out = array();

    $out[] = '<div style=" background: #E5F1FF; width: 600px; border-radius: 6px; margin: 10px auto; padding: 1px 20px 20px 20px;">';

    foreach($fields as $field => $label)
    {
        $out[] = '<div style="margin: 25px 0px 10px 0px;">';
        $out[] = '    <div style="color: #595959; font-size: 17px; font-weight: bold; font-family: Verdana;">'.$label.'</div>';
        $out[] = '</div>';
        $out[] = '<div style=" background: #fff; border-radius: 6px; padding: 10px; margin-top: 5px; line-height: 25px; text-align: justify; ">';
        $out[] = '    <div class="text" style="color: #595959; font-size: 16px; font-style: italic; font-family: Verdana;">'.nl2br($_REQUEST[$field]).'</div>';
        $out[] = '</div>';
    }

    $out[] = '</div>';

    return implode("\n", $out);
}
?>