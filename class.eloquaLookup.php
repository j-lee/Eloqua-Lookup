<?php
/***************************************************************************************************
 *
 * Eloqua Server-Side Data Lookup v1.0 (August 19, 2010)
 * http://code.google.com/p/eloqua-lookup/
 *
 * Copyright 2010 James Lee
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0

 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 **************************************************************************************************/

class EloquaLookup {
	protected $lookupURL = 'http://now.eloqua.com/visitor/v200/svrGP.aspx?pps=50';  // eloqua's lookup url
	protected $jsdata = NULL;  // javascript content returned from Eloqua

	public $data = NULL;  // parse eloqua javascript data placed into array
	public $siteID = NULL;  // int: site instance id on Eloqua
	public $key = NULL;  // string: data lookup key
	public $criteria = NULL;  // string|array: criteria for data lookup
	public $GUID = NULL;  // string: GUID for visitor lookup

	// returns data safely from data array by name (only valid for contacts/prospects/visitors lookup)
	public function getField($name) {
		return isset($this->data[$name]) ? $this->data[$name] : '';
	}

	// perform eloqua data lookup
	protected function eloquaLookup() {
		$url = $this->lookupURL;
		if (gettype($this->criteria) === 'string') {
			$url .= '&DLLookup='.urlencode($this->criteria);
		}
		elseif (gettype($this->criteria) === 'array') {
			$criteria = '';
			foreach ($this->criteria as $key=>$value) {
				$criteria .= '<'.$key.'>'.$value.'</'.$key.'>';
			}
			$url .= '&DLLookup='.urlencode($criteria);
		}
		$ch = curl_init();
		if ($this->GUID) {
			$header[] = 'Host: now.eloqua.com';  // necessary for Eloqua to read the cookie
			$header[] = 'Cookie: ELOQUA=GUID='.$this->GUID.'; ELQSTATUS=OK';
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FAILONERROR, FALSE);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);  // disallow redirects
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$this->jsdata = curl_exec($ch);
		curl_close($ch);
		return $this->parseData($this->jsdata);
	}

	// parse javascript data from Eloqua
	protected function parseData($data) {
		// check if certain keywords exists in returned data. if so, valid data was return
		if (strpos($data, 'strTemp') > 0) {  // indicates contacts/prospects/visitor lookup
			preg_match_all("/if\(strDataField == '(\w+)' \|\| strDataField == '(\w+)'\){strTemp = '(.*)';}/", $data, $matches, PREG_SET_ORDER);
			// put data into array
			if (count($matches) > 0) {
				$data = array();
				foreach ($matches as $match) {
					$value = preg_replace("#(\\\x[0-9A-F]{2})#e", "chr(hexdec('\\1'))", $match[3]); // converts utf-8 back to its printable character
					$data[$match[1]] = $value;
					$data[$match[2]] = $value;
				}
				$this->data = $data;
				return TRUE;
			}
		}
		elseif (strpos($data, 'blnTemp') > 0) {  // indicates contact group memberships lookup
			preg_match_all("/if\(strContactGroupGUID.replace\('{', ''\).replace\('}', ''\).toLowerCase\(\) == '(.*)'\){blnTemp = true;}/", $data, $matches, PREG_SET_ORDER);
			if (count($matches) > 0) {
				$data = array();
				foreach ($matches as $match)
					$data[] = $match[1];
				$this->data = $data;
				return TRUE;
			}
		}
		else $this->jsdata = NULL;
		return FALSE;
	}
}


// class to initialize contact lookup
class EloquaContactLookup extends EloquaLookup {
 	public function __construct($siteID, $key, $criteria=NULL) {
		$this->criteria = $criteria;
		$this->lookupURL = $this->lookupURL.'&siteid='.urlencode(intval($siteID)).'&DLKey='.urlencode($key).'&elqCookie=1';
		$this->eloquaLookup();
	}
}


// class to initialize prospect lookup
class EloquaProspectLookup extends EloquaLookup {
 	public function __construct($siteID, $key, $criteria=NULL) {
		$this->criteria = $criteria;
		$this->lookupURL = $this->lookupURL.'&siteid='.urlencode(intval($siteID)).'&DLKey='.urlencode($key).'&elqCookie=1';
		$this->eloquaLookup();
	}
}


// class to initialize visitor lookup
class EloquaVisitorLookup extends EloquaLookup {
 	public function __construct($siteID, $key, $GUID=NULL, $criteria=NULL) {
		$this->GUID = strtoupper( preg_replace('/[^a-fA-f0-9\s]/', '', $GUID) );
		$this->criteria = $criteria;
		$this->lookupURL = $this->lookupURL.'&siteid='.urlencode(intval($siteID)).'&DLKey='.urlencode($key);
		$this->eloquaLookup();
	}
}


// class to initialize contact group memberships lookup
class EloquaMembershipsLookup extends EloquaLookup {
 	public function __construct($siteID, $key, $criteria=NULL) {
		$this->criteria = $criteria;
		$this->lookupURL = $this->lookupURL.'&siteid='.urlencode(intval($siteID)).'&DLKey='.urlencode($key).'&elqCookie=1';
		$this->eloquaLookup();
	}
}