<?php

class language
{
	public $id;
	public $code;
	public $name;
	public $file;
	
	public $lang;
		
	/**
	 * Load the translations dictionary of the Navigate CMS interface
	 *
	 * @param string $code Language code (2 letters)
	 */		
	public function load($code='en')
	{
		global $DB;
				
		$DB->query('SELECT * FROM nv_languages WHERE code = '.protect($code));
		
		$data = $DB->first();		
		
		$this->id 	= $data->id;
		$this->code = $data->code;
		$this->name = $data->name;
		$this->file = $data->nv_dictionary;
			
		$langdata = @file_get_contents(NAVIGATE_PATH.'/'.$this->file);
		$langdata = explode("\n", $langdata);
		
		if(empty($langdata))
		{
			// failsafe language
			$this->lang = array(); 
			return;
		}
		
		foreach($langdata as $langline)
		{
			$langline = explode('#', $langline, 2);
			if(empty($langline[1])) continue;
			$this->lang[trim($langline[0])] = trim($langline[1]);
		}
	}

	/**
	 * Get the translating string for a code and returns it.
	 *
	 * @param string $id Codename of the string to get the translation
	 * @param string $default Text that will be shown if the current language does not have a translation for this word.
	 * @param array Replace a variable in the translated text for the given value.
	 */			
	public function t($id, $default="", $replace=array())
	{
		if(empty($this->lang[$id])) $out = $default;
		else						$out = $this->lang[$id];

        if(!empty($replace))
        {
            foreach($replace as $key => $val)
                $out = str_replace($key, $val, $out);
        }
		
		return $out;
	}

	/**
	 * Load the name of all available languages in the active language.
	 *
	 * @param boolean $with_dictionary
	 * @param boolean $return_as_object
	 * @return array $languages Array of language code => language name
	 */			
	public static function language_names($with_dictionary = true, $return_as_object = false)
	{
		global $DB;
		
		if($with_dictionary)
			$DB->query('SELECT code, name FROM nv_languages WHERE nv_dictionary != ""');		
		else
			$DB->query('SELECT code, name FROM nv_languages');		
		$data = $DB->result();	
		$languages = array();

        if($return_as_object)
            return $data;
		
		foreach($data as $row)
			$languages[$row->code] = $row->name;
		
		return $languages;
	}

	/**
	 * Return the local name for a language via its 2-letters code.
	 *
	 * @param string $code Language code (2 letters)
	 * @return $language_name The name of the lanaguage.
	 */		
    public static function name_by_code($code)
    {
        global $world_languages;

        if(empty($world_languages))
            $world_languages = language::language_names(false);

        if(strpos($code, '_') > 0)
        {
            $code = explode('_', $code);
            $name = $world_languages[$code[0]];
            $name.= ' ('.$code[1].')';
        }
        else
            $name = $world_languages[$code];

        return $name;
    }
}

?>