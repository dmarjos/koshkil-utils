<?php
namespace Koshkil\Utilities;

class DateUtilities {
	public const MINUTES	= 0x0001;
	public const HOURS		= 0x0002;
	public const DAYS		= 0x0003;

	private $time;
	private $defaultFormat="";

	public static function formatDateTime($dateTime, $fromFormat = "dd/mm/yy", $toFormat = "yyyy-mm-dd") {
		$expressionGroups = array (
				"DD" => "([0-9]{2})",
				"D" => "([0-9]{1,2})",
				"MM" => "([0-9]{2})",
				"M" => "([0-9]{1,2})",
				"YY" => "([0-9]{2})",
				"YYYY" => "([0-9]{4})",
				"H" => "([0-9]{2})",
				"I" => "([0-9]{2})",
				"S" => "([0-9]{2})"
		);

		$parsedFormat = preg_match_all ( "#(([DMYHIS]{1,4})([\-/:\s\.]?))#si", $fromFormat, $matches, PREG_SET_ORDER );
		if (! $matches)
			return $dateTime;

		$elements = array ();
		$toGroups = array ();
		$fromMask = "";
		foreach ( $matches as $position => $match ) {
			$selector = strtoupper ( $match [2] );
			$separador = $match [3];
			switch ($separador) {
				case "-" :
					$separador = "\-";
					break;
				case " " :
					$separador = "\s";
					break;
			}
			$fromMask .= $expressionGroups [strtoupper ( $selector )] . $separador;
			$elements [$position + 1] = $selector;
		}

		$regExp = ";" . $fromMask . ";";
		if (preg_match_all ( $regExp, $dateTime, $matches, PREG_SET_ORDER )) {
			foreach ( $matches [0] as $position => $match ) {
				if ($position == 0)
					continue;
				$groupIdentifier = $elements [$position];
				$toGroups [$groupIdentifier] = $match;
			}
			$parsedFormat = preg_match_all ( "#(([DMYHIS]{1,4})([\-/:\s\.]?))#si", $toFormat, $matches, PREG_SET_ORDER );
			if (! $matches)
				return $dateTime;

			$retVal = array ();
			foreach ( $matches as $position => $match ) {
				$selector = strtoupper ( $match [2] );
				$separador = $match [3];
				$retVal [] = $toGroups [$selector];
				$retVal [] = $separador;
			}
			return implode ( "", $retVal );
		}

		return $dateTime;
	}

	public function elapsed($datetime=null, $full=false) {

		$l['rel_justnow'] 				 = "Reci&eacute;n";
		$l['rel_less_than']				 = "Menos de un minuto";
		$l['rel_second_single']			 = "seg.";
		$l['rel_second_plural']			 = "seg.";
		$l['rel_minute_single']			 = "min.";
		$l['rel_minute_plural']			 = "min.";
		$l['rel_day_single']			 = "d&iacute;a";
		$l['rel_day_plural']			 = "d&iacute;as";
		$l['rel_hour_single']			 = "hora";
		$l['rel_hour_plural']			 = "horas";
		$l['rel_month_single']		 	 = "mes";
		$l['rel_month_plural']		 	 = "meses";
		$l['rel_year_single']			 = "a&ntilde;o";
		$l['rel_year_plural']			 = "a&ntilde;os";
		$l['rel_format']				 = "{1} {2}";

		$stamp=$datetime?strtotime($datetime):time();
		$timenow=$this->time;

		$diff = ($timenow>$stamp) ? (int)$timenow-$stamp : (int)$stamp-$timenow;

		$over = 'atr&aacute;s';

		$formatter['year']		= 31104000;		$how_much['year']		= '100';
		$formatter['month']		= 2592000;		$how_much['month']		= '12';
		$formatter['day']		= 86400;		$how_much['day']		= '30';
		$formatter['hour']		= 3600;			$how_much['hour']		= '24';
		$formatter['minute']	= 60;			$how_much['minute']		= '60';
		$formatter['second']	= 1;			$how_much['second']		= '60';

		if ($diff>($formatter["day"]*2)) {
			$fh=explode(" ",$datetime);
			return trim(implode("/",array_reverse(explode('-',$fh[0])))." ".$fh[1]);
		}

		foreach($formatter as $date => $overstamp) {
			$calc[$date] = floor($diff/$overstamp) % $how_much[$date];
			$sp = ($calc[$date] == "1") ? "single" : "plural";

			if($calc[$date] == 0) {
				if($date != 'second') {
					unset($calc[$date]);
				}
			} else {
				$tostring[$date] = $calc[$date]." ".$l['rel_'.$date.'_'.$sp];
			}
			$reminder = $date;
		}


		if(count($calc) == '1' && $reminder = 'seconds') {
			if($calc['second'] == '0') {
				$tostring[$date] = $l['rel_justnow'];
			} else {
				$tostring[$date] = $l['rel_less_than'];
			}
		} else {
			if ($calc[$date]." ".$l['rel_'.$date.'_'.$sp]!='0 seg.')
				$tostring[$date] = stringUtils::sprintf($l['rel_format'], $calc[$date]." ".$l['rel_'.$date.'_'.$sp], $l['rel_'.$over]);
		}

		if($full == false) {
			$display = stringUtils::sprintf($l['rel_format'], array_shift(array_slice($tostring, 0, 1)), $l['rel_'.$over]);
		} else {
			$display = implode(", ", $tostring);
		}

		return $display;
	}

	public function __construct($dateString="",$defaultFormat="d/m/Y H:i:s") {
		if (!$dateString) $dateString=date($defaultFormat,time());
		$this->defaultFormat=$defaultFormat;
		if (preg_match_all('~([0-9]{10,15})~si',$dateString,$matches)) {
			$this->time=intVal($dateString);
		} else if (preg_match_all('~([0-9]{2,4})[-/]([0-9]{2})[-/]([0-9]{2,4})~si',$dateString,$matches) || preg_match_all('~([0-9]{2,4})[-/]([0-9]{2})[-/]([0-9]{2,4}) ([0-2]{1})([0-9]{1}):([0-9]{2}):([0-9]{2})~si',$dateString)) {
			$this->time=strtotime($dateString);
		} else {
			KoshkilLog::error('The provided string is not a valid timestamp or a valid date time: ',$dateString);
			throw new EDateNotValidException('The provided string is not a valid timestamp or a valid date time: '.$dateString);
		}
	}

	public function addMinutes($minutes) {
		return new datesUtils($this->time+($minutes*60));
	}

	public function addHours($hours) {
		return new datesUtils($this->time+($hours*3600));
	}

	public function addDays($days) {
		return new datesUtils($this->time+($days*(24*3600)));
	}

	public function dayOfWeek() {
		return date("w",$this->time);
	}

	public function hour() {
		return date("G",$this->time);
	}

	public function minutes() {
		return date("i",$this->time);
	}

	public function day() {
		return date("j",$this->time);
	}

	public function month() {
		return date("n",$this->time);
	}

	public function year() {
		return date("Y",$this->time);
	}

	public function format($format="") {
		return date($format?:$this->defaultFormat,$this->time);
	}

	public function __toString() {
		return $this->format();
	}
}
