<?php

namespace Nosdave {
	class Sluggify {
		function BuildUniqueSlug($object, &$totalSlugs)
		{
			$sluggableKeys = ['name', 'title', 'title1', 'title2', 't', "description", 'desc', 'internalId', '0', '1', '2', '3', '4', '5'];
		
			$currentSlug = "";
			$currentTryKeyIndex = 0;
			for($i = 0; $i < count($sluggableKeys); $i++) {
				$currentSlugKey = $sluggableKeys[$i];
				
				if (isset($object[$currentSlugKey])) {
					//constrain urlslug to 48 chars
					$currentSlug = mb_substr($currentSlug, 0, min(48, mb_strlen($currentSlug)));

					if (is_string($object[$currentSlugKey])) {
						if (mb_strlen($currentSlug) > 0) $currentSlug .= '-';
						$currentSlug .= self::UrlEncodeString($object[$currentSlugKey]);
					} else if (is_array($object[$currentSlugKey])) {
						$currentSlug .= self::UrlEncodeString(implode("-", $object[$currentSlugKey]));
					} else if (is_numeric($object[$currentSlugKey])) {
						$currentSlug .= $object[$currentSlugKey];
					}

					//constrain urlslug to 64 chars
					$currentSlug = mb_substr($currentSlug, 0, min(64, mb_strlen($currentSlug)));
			
					//make sure no trailing -
					while (mb_substr($currentSlug, mb_strlen($currentSlug) - 1) == '-') {
						$currentSlug = mb_substr($currentSlug, 0, mb_strlen($currentSlug) - 1);
					}

					if (!in_array($currentSlug, $totalSlugs) && !empty($currentSlug)) {
						//unique slug
						array_push($totalSlugs, $currentSlug);
						return $currentSlug;
					}
				}
			}

			$currentSlug = self::generateRandomString(32);
			array_push($totalSlugs, $currentSlug);
			return $currentSlug;
		}	
		
		private static function generateRandomString($length = 10) {
			$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$charactersLength = strlen($characters);
			$randomString = '';
			for ($i = 0; $i < $length; $i++) {
				$randomString .= $characters[rand(0, $charactersLength - 1)];
			}
			return $randomString;
		}

		private static function UrlEncodeString($str, $options = array()) {
			// Make sure string is in UTF-8 and strip invalid UTF-8 characters
			$str = mb_convert_encoding((string)$str, 'UTF-8', mb_list_encodings());
			
			$defaults = array(
				'delimiter' => '-',
				'limit' => null,
				'lowercase' => true,
				'replacements' => array(),
				'transliterate' => false,
			);
			
			// Merge options
			$options = array_merge($defaults, $options);
			
			$char_map = array(
				// Latin
				'??' => 'A', '??' => 'A', '??' => 'A', '??' => 'A', '??' => 'A', '??' => 'A', '??' => 'AE', '??' => 'C', 
				'??' => 'E', '??' => 'E', '??' => 'E', '??' => 'E', '??' => 'I', '??' => 'I', '??' => 'I', '??' => 'I', 
				'??' => 'D', '??' => 'N', '??' => 'O', '??' => 'O', '??' => 'O', '??' => 'O', '??' => 'O', '??' => 'O', 
				'??' => 'O', '??' => 'U', '??' => 'U', '??' => 'U', '??' => 'U', '??' => 'U', '??' => 'Y', '??' => 'TH', 
				'??' => 'ss', 
				'??' => 'a', '??' => 'a', '??' => 'a', '??' => 'a', '??' => 'a', '??' => 'a', '??' => 'ae', '??' => 'c', 
				'??' => 'e', '??' => 'e', '??' => 'e', '??' => 'e', '??' => 'i', '??' => 'i', '??' => 'i', '??' => 'i', 
				'??' => 'd', '??' => 'n', '??' => 'o', '??' => 'o', '??' => 'o', '??' => 'o', '??' => 'o', '??' => 'o', 
				'??' => 'o', '??' => 'u', '??' => 'u', '??' => 'u', '??' => 'u', '??' => 'u', '??' => 'y', '??' => 'th', 
				'??' => 'y',
		
				// Latin symbols
				'??' => '(c)',
		
				// Greek
				'??' => 'A', '??' => 'B', '??' => 'G', '??' => 'D', '??' => 'E', '??' => 'Z', '??' => 'H', '??' => '8',
				'??' => 'I', '??' => 'K', '??' => 'L', '??' => 'M', '??' => 'N', '??' => '3', '??' => 'O', '??' => 'P',
				'??' => 'R', '??' => 'S', '??' => 'T', '??' => 'Y', '??' => 'F', '??' => 'X', '??' => 'PS', '??' => 'W',
				'??' => 'A', '??' => 'E', '??' => 'I', '??' => 'O', '??' => 'Y', '??' => 'H', '??' => 'W', '??' => 'I',
				'??' => 'Y',
				'??' => 'a', '??' => 'b', '??' => 'g', '??' => 'd', '??' => 'e', '??' => 'z', '??' => 'h', '??' => '8',
				'??' => 'i', '??' => 'k', '??' => 'l', '??' => 'm', '??' => 'n', '??' => '3', '??' => 'o', '??' => 'p',
				'??' => 'r', '??' => 's', '??' => 't', '??' => 'y', '??' => 'f', '??' => 'x', '??' => 'ps', '??' => 'w',
				'??' => 'a', '??' => 'e', '??' => 'i', '??' => 'o', '??' => 'y', '??' => 'h', '??' => 'w', '??' => 's',
				'??' => 'i', '??' => 'y', '??' => 'y', '??' => 'i',
		
				// Turkish
				'??' => 'S', '??' => 'I', '??' => 'C', '??' => 'U', '??' => 'O', '??' => 'G',
				'??' => 's', '??' => 'i', '??' => 'c', '??' => 'u', '??' => 'o', '??' => 'g', 
		
				// Russian
				'??' => 'A', '??' => 'B', '??' => 'V', '??' => 'G', '??' => 'D', '??' => 'E', '??' => 'Yo', '??' => 'Zh',
				'??' => 'Z', '??' => 'I', '??' => 'J', '??' => 'K', '??' => 'L', '??' => 'M', '??' => 'N', '??' => 'O',
				'??' => 'P', '??' => 'R', '??' => 'S', '??' => 'T', '??' => 'U', '??' => 'F', '??' => 'H', '??' => 'C',
				'??' => 'Ch', '??' => 'Sh', '??' => 'Sh', '??' => '', '??' => 'Y', '??' => '', '??' => 'E', '??' => 'Yu',
				'??' => 'Ya',
				'??' => 'a', '??' => 'b', '??' => 'v', '??' => 'g', '??' => 'd', '??' => 'e', '??' => 'yo', '??' => 'zh',
				'??' => 'z', '??' => 'i', '??' => 'j', '??' => 'k', '??' => 'l', '??' => 'm', '??' => 'n', '??' => 'o',
				'??' => 'p', '??' => 'r', '??' => 's', '??' => 't', '??' => 'u', '??' => 'f', '??' => 'h', '??' => 'c',
				'??' => 'ch', '??' => 'sh', '??' => 'sh', '??' => '', '??' => 'y', '??' => '', '??' => 'e', '??' => 'yu',
				'??' => 'ya',
		
				// Ukrainian
				'??' => 'Ye', '??' => 'I', '??' => 'Yi', '??' => 'G',
				'??' => 'ye', '??' => 'i', '??' => 'yi', '??' => 'g',
		
				// Czech
				'??' => 'C', '??' => 'D', '??' => 'E', '??' => 'N', '??' => 'R', '??' => 'S', '??' => 'T', '??' => 'U', 
				'??' => 'Z', 
				'??' => 'c', '??' => 'd', '??' => 'e', '??' => 'n', '??' => 'r', '??' => 's', '??' => 't', '??' => 'u',
				'??' => 'z', 
		
				// Polish
				'??' => 'A', '??' => 'C', '??' => 'e', '??' => 'L', '??' => 'N', '??' => 'o', '??' => 'S', '??' => 'Z', 
				'??' => 'Z', 
				'??' => 'a', '??' => 'c', '??' => 'e', '??' => 'l', '??' => 'n', '??' => 'o', '??' => 's', '??' => 'z',
				'??' => 'z',
		
				// Latvian
				'??' => 'A', '??' => 'C', '??' => 'E', '??' => 'G', '??' => 'i', '??' => 'k', '??' => 'L', '??' => 'N', 
				'??' => 'S', '??' => 'u', '??' => 'Z',
				'??' => 'a', '??' => 'c', '??' => 'e', '??' => 'g', '??' => 'i', '??' => 'k', '??' => 'l', '??' => 'n',
				'??' => 's', '??' => 'u', '??' => 'z'
			);
			
			// Make custom replacements
			$str = preg_replace(array_keys($options['replacements']), $options['replacements'], $str);
			
			// Transliterate characters to ASCII
			if ($options['transliterate']) {
				$str = str_replace(array_keys($char_map), $char_map, $str);
			}
			
			// Replace non-alphanumeric characters with our delimiter
			$str = preg_replace('/[^\p{L}\p{Nd}]+/u', $options['delimiter'], $str);
			
			// Remove duplicate delimiters
			$str = preg_replace('/(' . preg_quote($options['delimiter'], '/') . '){2,}/', '$1', $str);
			
			// Truncate slug to max. characters
			$str = mb_substr($str, 0, ($options['limit'] ? $options['limit'] : mb_strlen($str, 'UTF-8')), 'UTF-8');
			
			// Remove delimiter from ends
			$str = trim($str, $options['delimiter']);
			
			return $options['lowercase'] ? mb_strtolower($str, 'UTF-8') : $str;
		}
	}
}