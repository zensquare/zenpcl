<?php
/*
 * Zensquare PHP Cron Library (ZenPCL)
 * 
 * Copyright (C) 2015 Nick Rechten, All rights reserved.
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3.0 of the License, or (at your option) any later version.
 * 
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.
 *
*/

/*
 * Code igniter doesn't support name spaces - this wrapper allows the cron class
 * to be used as a code ignitier library. If you are not using code igniter feel
 * free to remove this section 
 */
namespace {
    class Cron {
        
        public function parse($expression){
            $cron = new ZenPCL\Cron();
            $cron->parse($expression);
            return $cron;
        }
        
    }
    
}

namespace ZenPCL {

const FIELD_MINUTES =  0;
const FIELD_HOURS = 1;
const FIELD_DAY_OF_MONTH = 2;
const FIELD_MONTH = 3;
const FIELD_DAY_OF_WEEK = 4;
const FIELD_YEAR = 5;

class CDate {

    public static $months = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
    public static $names = array("January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");
    public $y;
    public $m;
    public $d;
    public $h;
    public $i;
    public $s;

    public function __construct($date=null) {
        if($date == null){
            $date = new \DateTime();
        } else if(is_string($date)) {
            $date = new \DateTime($date);
        }
        list($this->y, $this->m, $this->d, $this->h, $this->i, $this->s) = explode(" ", $date->format('Y m d H i s'));
        $this->s = 0; //Until i implement seconds
    }

    function dow() {
        $year = $this->y;
        $month = $this->m;
        $day = $this->d;

        if ($month <= 2) {
            $month += 12;
            $Y = $year - 1;
        } else {
            $Y = $year;
        }
        $y = $Y % 100;
        $c = ($Y - $y) / 100;

        return (($day + floor((13 * ($month + 1) / 5)) + $y + floor($y / 4) + floor($c / 4) + 5 * $c) + 6) % 7;
    }

    public function daysInMonth() {
        return $this::$months[$this->m % 12];
    }

    public function isLeapYear($year) {
        return $year % 400 == 0 || ($year % 100 != 0 && $year % 4 == 0);
    }

    function addMinutes($minutes){
        $this->i += $minutes;
        while($this->i >=60){
            $this->i -= 60;
            $this->addHours(1);
        }
    }
    
    function addHours($hours){
        $this->h += $hours;
        while($this->h>=24){
            $this->h -= 24;
            $this->addDays(1);
        }
    }
    
    function addDays($days) {
        $this->d = $this->d + $days;
        $this->m = $this->m % 12; //Just incase
        while ($this->d > CDate::$months[$this->m] + ($this->m == 1 && $this->isLeapYear($this->y) ? 1 : 0)) {
            $this->d -= CDate::$months[$this->m];
            $this->addMonths(1);
        }
    }
    
    function addMonths($months){
        $this->m += $months;
        while ($this->m >= 12) {
            $this->y++;
            $this->m -= 12;
        }
    }

    function toDateTime(){
        return sprintf("%04d-%02d-%02d %02d:%02d:%02d",$this->y,$this->m,$this->d,$this->h,$this->i,$this->s);
    }
}

class Cron {

    protected $fields = array();
    protected $parts = array();
    public static  $ids = 0;
    public $last = null;
    
    public function __construct() {
        $this->fields = array(
            new MinutesField("Minute", "1"),
            new HourField("Hour", "1"),
            new DayOfMonthField("Day of Month", "*", $this),
            new MonthField("Month", "*"),
            new DayOfWeekField("Day of Week", "*", $this),
            new YearField("Year", "*")
        );
        foreach($this->fields as $field){
            $this->addFieldPart($field);
        }
    }

    private function addFieldPart($part) {
        Cron::$ids++;
        $part->id = Cron::$ids;
        $this->parts[Cron::$ids] = $part;
    }

    public function parse($expression) {
        $cron = explode(" ", trim($expression));
        for ($i = 0; $i < count($cron) && $i < count($this->fields); $i++) {
            $this->fields[$i]->parse((is_null($cron[$i])||$cron[$i]=="")?'*':$cron[$i]);
        }
    }

    public function toString($newline = true) {
        $foo = "";
        $nl = $newline ? "\n" : " ";

        foreach ($this->fields as $field) {
            $f = trim($field->toString());
            if (!empty($f)) {
                $foo .= $f . $nl;
            }
        }
        return trim($foo);
    }

    public function toMarkup() {
        $foo = "";
        foreach ($this->fields as $field) {
            $foo .= $field->toMarkup();
        }
        return $foo;
    }

    public function toCron() {
        $foo = "";
        foreach ($this->fields as $field) {
            $foo .= $field->toCron();
        }
        return $foo;
    }

    public function fieldMarkup($field) {
        return "<div class=\"cron_column\"><div class=\"column_name\">" . $field->name . " filter</div><div class=\"rules\">" . $field->toMarkup() . "<div class=\"rule\">" . $field->getAddText() . "</div></div></div>";
    }

    public function addToField($id, $newPart) {
        foreach ($this->fields as $field) {
            if ($field->id == $id) {
                $field->add($newPart);
                return;
            }
        }
        if (isset($this->parts[$id])) {
            $fp = $this->parts[$id];
            $fp->parent->replace($fp, $fp->field->parseField($newPart, $fp->parent));
        }
    }

    public function setField($id, $newPart) {
        if (isset($this->parts[$id])) {
            $fp = $this->parts[$id];
            $fp->parent->replace($fp, $fp->field->parseField($newPart, $fp->parent));
        }
    }

    public function remove($id) {
        if (isset($this->parts[$id])) {
            $fp = $this->parts[$id];
            $fp->parent->replace($fp, new WildCardFieldPart($fp->parent));
        }
    }

    public function getOptions($id) {
        if (isset($this->parts[$id])) {
            $fp = $this->parts[$id];
            $sf = $fp->field;
            $options = array();
            for ($i = $sf->lower; $i <= $sf->upper; $i++) {
                $options[0][] = $i;
                $options[1][] = $sf->getValue($i);
            }
            return options;
        }
        return array(array("0"), array("No options"));
    }

    public function nextValid($date = null, $forceNext=true) {
        if(empty($date)){
            $date = $this->last;
            $forceNext = true;
        }
        
        if (empty($date)) {
            $date = new CDate();
        }
        if(!$date instanceof CDate){
            $date = new CDate($date);
        }
        
        
        if($forceNext){
            $date->addMinutes(1);
        }
//        echo "Checking from ".$date->toDateTime()."\n";
        
        $valid = false;
        $fields = $this->fields;
        while (!$valid) {            
            $valid = true;
//            echo "\nL:";
            for ($i = count($fields) - 1; $i >= 0; $i--) {
//                echo $i;
                $field = $fields[$i];
                
                if ($date->y > 2100 || ($i == FIELD_YEAR && !$field->getNextValid($date))) {
                    return FALSE;
                }
//                echo get_class($field) . "|";
                
                if (!$field->getNextValid($date)) {
                    if ($field instanceof MonthField) {
                        $fields[$i + 2]->roll($date);
                    } else if (!($field instanceof DayOfWeekField)) {
                        $fields[$i + 1]->roll($date);
//                        echo " Roll : " . get_class($fields[$i + 1]);
                    }
                    $valid = false;
                    break;
                }
            }
        }
        
        $this->last = $date;
        return $date->toDateTime();
    }

}

interface PartParent {

    public function replace($target, $replacement);

    public function relation($part);
}

abstract class ScheduleField implements PartParent {

    public $name;
    public $value;
    public $lower;
    public $upper;
    public $in_on = "in ";
    public $prefix = "the ";
    public $id;
    public $upperQuantifier = "";
    public $quantifier = "";

    public function __construct($name, $quantifier, $upperQuantifier, $value, $lower, $upper, $in_on = "in ") {
        $this->name = $name;
        $this->upper = $upper;
        $this->lower = $lower;
        $this->value = $this->parseField($value, $this);
        $this->quantifier = $quantifier;
        $this->upperQuantifier = $upperQuantifier;
        $this->id = Cron::$ids++;
    }

    public function getValue($value) {
        return $this->addThing( $value+1);
    }

    public function addThing($value) {
        $thing = "th";
        if ($value % 100 > 10 && $value % 100 < 14) {
            
        } else if ($value % 10 == 1) {
            $thing = "st";
        } else if ($value % 10 == 2) {
            $thing = "nd";
        } else if ($value % 10 == 3) {
            $thing = "rd";
        }
        return $value . $thing;
    }

    public function toString() {
        if ($this->value instanceof CompoundFieldPart) {
            return $this->value->toString();
        }
        if ($this->value instanceof BasicFieldPart) {
            return $this->in_on . $this->prefix . $this->value->toString() . " " . $this->getQuantifier();
        }
        return $this->value->toString() . "\n";
    }

    public function toMarkup() {
        if ($this->value instanceof CompoundFieldPart) {
            return $this->value->toMarkup();
        }
        if ($this->value instanceof WildcarFieldPart) {
            return $this->getWildcardText();
        }
        return $this->formatRule($this->value);
    }

    public function toCron() {
        return $this->value->toCron();
    }

    public function add($part) {
        if ($this->value instanceof CompoundFieldPart) {
            $this->value->addPart($this->parseField($part, $this->value));
        } else if ($this->value instanceof WildcardFieldPart) {
            $this->value->field = null;
            $this->value = $this->parseField($part, $this);
        } else {
            $cfp = new CompoundFieldPart();
            $cfp->field = $this;
            $cfp->parent = $this;
            $cfp->addPart($this->value);
            $cfp->addPart($this->parseField($part, $cfp));
            $this->value = $cfp;
        }
    }

    public function parse($field){
        $this->value = $this->parseField($field, $this);
    }
    
    public function parseField($field, $parent = null) {
        if (empty($parent)) {
            $parent = $this;
        }

        $parts = explode(",", $field);
        if (count($parts) > 0) {
            if (count($parts) > 1) {
                $cfp = new CompoundFieldPart();
                $cfp->field = $this;
                $cfp->parent = $parent;
                foreach ($parts as $part) {
                    $cfp->addPart(parseField($part, $cfp));
                }
            } else {
                $part = $parts[0];
                if (strpos($part, "/") !== FALSE) {
                    $ipart = new IncrementPart();
                    $ipart->field = $this;
                    $ipart->parent = $parent;
                    $subparts = explode("/", $part, 2);
                    $ipart->setRange($this->parseField($subparts[0], $ipart));
                    $ipart->setIncrement($this->parseField($subparts[1], $ipart));
                    return $ipart;
                }
                if (strpos($part, "-") !== FALSE) {
                    $rpart = new RangePart();
                    $rpart->field = $this;
                    $rpart->parent = $parent;
                    $subparts = explode("-", $part, 2);
                    $rpart->setLower($this->parseField($subparts[0], $rpart));
                    $rpart->setUpper($this->parseField($subparts[1], $rpart));
                    return $rpart;
                }
                if ($part == "*" || $part == "?") {
                    return new WildcardFieldPart($this, $parent);
                }
                return new BasicFieldPart($this->bound($part), $this, $parent);
            }
        }
        return null;
    }

    public function bound($value) {

        if ($value < $this->lower) {
            return $this->lower;
        }
        if ($value > $this->upper) {
            return $this->upper;
        }
        return $value;
    }

    public function formatBetween($range, $withMarkup = false) {
        $func = $withMarkup ? "toMarkup" : "toString";
        return "between " . $this->prefix . $range->lower->{$func}() . " and " . $range->upper->{$func}() . " " . $this->getQuantifier();
    }

    public function formatIncrement($field, $withMarkup = false) {
        if ($field instanceof WildcardFieldPart) {
            return "";
        }

        $inc = "";
        if ($field instanceof BasicFieldPart) {
            if ($field->value == 1) {
                $inc .= $this->quantifier . " ";
            } else {
                $inc .= $this->addThing($field->value) . " " . $this->quantifier . " ";
            }
        } else {
            $inc .= $withMarkup ? $field->toMarkup() : $field->toString();
        }
        return " every " . ($withMarkup ? $field->markup($inc) : $inc );
    }

    public function getWildcardText() {
        return "";
    }

    public function getAddText() {
        return "<a href=\"add:" . $this->id . "\" class=\"new\">[+] Add new Rule</a>";
    }

    public function formatRule($part) {
        if ($part instanceof BasicFieldPart) {
            return "<div class=\"rule\"><a href=\"remove:" . $part->id . "\" class=\"remove\">[-] </a>" . $this->in_on . $this->prefix . $part->toMarkup() . " " . $this->getQuantifier() . "</div>";
        } else {
            return "<div class=\"rule\"><a href=\"remove:" . $part->id . "\" class=\"remove\">[-] </a>" . $part->toMarkup() . "</div>";
        }
    }

    public function replace($target, $replacement) {
        if ($this->value == $target) {
            $this->value = $replacement;
            $replacement->parent = $this;
            $replacement->field = $this;
        }
    }

    public function relation($part) {
        if ($this == $part->parent) {
            return "child";
        }
        return null;
    }

    public function getQuantifier() {
        return $this->quantifier . " of " . $this->prefix . $this->upperQuantifier;
    }

    public abstract function getNextValid($date);

    public abstract function roll($date);
}

class MinutesField extends ScheduleField {

    public function __construct($name, $value) {
        parent::__construct($name, 'minute', 'hour', $value, 0, 59);
        $this->in_on = "on ";
        $this->prefix = "the ";
    }

    public function getWildcardText() {
        return "every minute";
    }

    public function formatBetween($range, $withMarkup = false) {
        $func = $withMarkup ? "toMarkup" : "toString";
        return " between " . $this->prefix . $range->lower->{$func}() . " and " . $range->upper->{$func}() . " " . $this->getQuantifier();
    }

    public function getNextValid($date) {
        $minutes = $date->i;
        $next = $this->value->nextValid($minutes);

        
        
        if ($next < $minutes) {
            return false;
        }
        if($minutes != $next) {
            $date->i = $next;
            $date->s = 0;
        }

        return true;
    }

    public function roll($date) {
        $date->i = 1;
        $date->s = 0;
    }

}

class HourField extends ScheduleField {

    public function __construct($name, $value) {
        parent::__construct($name, 'hour', 'day', $value, 0, 23);
        $this->prefix = "";
        $this->in_on = "at ";
    }

    public function getValue($value) {
        if ($value == 0) {
            return "12am";
        }
        if ($value < 12) {
            return $value . "am";
        }
        if ($value == 12) {
            return "12pm";
        }
        return $value - 12 . "pm";
    }

    public function getQuantifier() {
        return "";
    }

    public function getNextValid($date) {
        $hour = $date->h;
        $next = $this->value->nextValid($hour);

        if ($next < $hour) {
            return false;
        }

        if($next != $hour){
            $date->h = $next;
            $date->i = 0;
            $date->s = 0;
        }
        return true;
    }

    public function roll($date) {
        $date->i = 0;
        $date->s = 0;
        $date->addHours(1);
    }

}

class DayOfWeekField extends ScheduleField {

    public static $DAYS_OF_WEEK = array("Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");
    public $cron;

    public function __construct($name, $value, $cron, $firstDOW = 0, $lastDOW = 6) {
        parent::__construct($name, 'day', 'week', $value, $firstDOW, $lastDOW);
        $this->prefix = "on ";
        $this->in_on = "a ";
        $this->cron = $cron;
    }

    public function getValue($value) {
        return DayOfWeekField::$DAYS_OF_WEEK[($value - $this->lower) % 7];
    }

    public function formatBetween($range, $withMarkup = false) {
        $func = $withMarkup ? "toMarkup" : "toString";
        return " from " . $range->lower->{$func}() . " to " . $range->upper->{$func}();
    }

    public function getAddText() {
        if ($this->cron->fields[FIELD_DAY_OF_MONTH] . value instanceof WildcardFieldPart) {
            return "<a href=\"add:" . $this->id . "\" class=\"new\">[+] Add new Rule</a>";
        } else {
            return "This cannot be set while a day of month filter is set";
        }
    }

    public function getNextValid($date) {
        $dow = $date->dow();
        $next = $this->value->nextValid($dow);

        if ($next != $dow) {
            $month = $date->m;
            $date->s = 0;
            $date->i = 0;
            $date->h = 0;
            $date->addDays(($next < 0 ? 7 - $dow - $next : $next - $dow));
            return $month == $date->m;
        }

        return true;
    }

    public function roll($date) {
        
    }

}

class DayOfMonthField extends ScheduleField {

    public function __construct($name, $value) {
        parent::__construct($name, 'day', 'month', $value, 1, 31);
        $this->in_on = "on ";
    }

    public function getValue($value) {
        return parent::getValue($value - 1);
    }

    public function getAddText() {
        if ($this->cron->fields[FIELD_DAY_OF_WEEK] . value instanceof WildcardFieldPart) {
            return "<a href=\"add:" . $this->id . "\" class=\"new\">[+] Add new Rule</a>";
        } else {
            return "This cannot be set while a day of week filter is set";
        }
    }

    public function getNextValid($date) {

        $daysInMonth = $date->daysInMonth();
        $dom = $date->d;
        $next = $this->value->nextValid($dom);

//        echo "\nDOM $daysInMonth : $dom : $next \n";
        
        if ($next < $dom || $next > $daysInMonth) {
            return false;
        }

        if($dom != $next){
            $date->d = $next;
            $date->h = 0;
            $date->i = 0;
            $date->s = 0;
        }
        
        return true;
    }
    
    public function roll($date) {
        $date->s = 0;
        $date->i = 0;
        $date->h = 0;
        $date->addDays(1);
    }
}

class MonthField extends ScheduleField {
    public function __construct($name, $value){
        parent::__construct($name, 'month', 'year', $value, 1, 12);
        $this->in_on = "in ";
        $this->prefix = "";
    }
    
    public function getValue($value){
        return CDate::$names[max(0, $value-1)%12];
    }
    
    public function getQuantifier(){
        return "";
    }
    
    public function getNextValid($date){
        $month = $date->m;
        $next = $this->value->nextValid($month+1)-1;
        
        if($next < $month){
            return false;
        }
        
        if($next != $month){
            $date->m = $next;
            $date->d = 1;
            $date->h = $date->i = $date->s = 0;
        }
        
        return true;
    }
    
    public function roll($date){
        $date->d = 1;
        $date->h = $date->i = $date->s = 0;
        $date->addMonths(1);
    }
}

class YearField extends ScheduleField {
    public function __construct($name, $value){
        parent::__construct($name, "year", "year", $value, date('Y'), date('Y') + 100);
        $this->in_on = "in ";
        $this->prefix = "";
    }
    
    public function getValue($value){
        return parent::getValue($value-1);
    }
    
    public function addThing($value){
        return $value;
    }
    
    public function getQuantifier(){
        return "";
    }
    
    public function getNextValid($date){
        $year = $date->y;
        $next = $this->value->nextValid($year);
        
        if($next < $year){
            return false;
        }
        
        if($next != $year){ 
            $date->y = $next;
            $date->m = $date->h = $date->i = $date->s = 0;
            $date->d = 1;
        }
        
        return true;
    }
    
    public function bound($value) {
        return $value>0?$value:0;
    }
    
    public function roll($date) {
        $date->y++;
        $date->m = $date->h = $date->i = $date->s = 0;
        $date->d = 1;
    }
}

abstract class FieldPart {
   public $field;
   public $id;
   public $parent;
   
   public function __construct(){
   }
   
   public abstract function toCron();
   public abstract function toMarkup();
   
   public function getQuantifier(){
       return $this->field->quantifier . " of the " . $this->field->upperQuantifier;
   }
   
   public function getFullDescription() {
       return $this->field->in_on . $this->field->prefix . $this->toString() . " " . $this->field->quantifier;
   }
   
   public function getIntValue(){
       return 0;
   }
   
   public function relation($part) {
       if($part == $this){
           return "this";
       }
       if($this == $part->parent){
           return "child";
       }
       return null;
   }
   
   public function markup($value){
            return "<a href=\"edit:" . $this->id . "\" class=\"" . get_class($this) . "\">" . $value . "</a>";
   }
   
   public function toString() {
       return $this->field->getValue($this->getIntValue());
   }
   
   public abstract function nextValid($date);
   
}

class BasicFieldPart extends FieldPart {
    public $value;
    
    public function __construct($value, $field){
        $this->value = $value;
        $this->field = $field;
    }
    
    public function toString(){
        return $this->field->getValue($this->value);
    }
    
    public function toMarkup(){
        return $this->markup($this->field->getValue($this->value));
    }
    
    public function getFullDescription() {
       return $this->field->in_on . $this->field->prefix . $this->toString() . " " . $this->getQuantifier();
    }
    
    public function getIntValue(){
        return $this->value;
    }
    
    public function toCron(){
        return $this->value;
    }
    
    public function nextValid($start){
        return $start > $this->value ? -$this->value : $this->value;
    }    
}

class CompoundFieldPart extends FieldPart implements PartParent {
    public $parts = array();
    
    public function toString(){
        $ret = "";
        for($i = 0; $i < count($this->parts); $i++){
            if($i != 0){
                if($i < count($this->parts) -1 ){
                    $ret .= ",";
                } else {
                    $ret .= " and ";
                }
            }
        }
        if($parts[i] instanceof BasicFieldPart) {
            $ret .= $this->field->in_on . $this->field->prefix . $this->parts[$i]->toString() . " " . $this->field->getQuantifier();
        } else {
            $ret .= $this->parts[$i]->toString();
        }
        return $ret;
    }
    
    public function toMarkup() {
        $ret = "";
        foreach($this->parts as $part){
            $ret .= $this->field->formatRule($part);
        }
        return $ret;
    }
    
    public function toCron(){
        $ret = "";
        foreach($this->parts as $part){
            $ret .= $ret == ""?"":",";
            $ret .= $part->toCron();
        }
    }
    
    public function addPart($part){
        $this->parts($part);
        $part->parent = $this;
        $part->field = $this->field;
    }
    
    public function removeField($part){
        if (($key = array_search($part, $this->parts)) !== false) {
            unset($this->parts[$key]);
            $this->parts = array_values($this->parts);
            $part->parent = null;
            $part->field = null;
        }
    }
    
    public function replace($target, $replacement){
        $this->removeField($target);
        if($replacement instanceof WildcardFieldPart){
            if(count($this->parts) == 1){
                $this->parent->replace($this, $this->parts[0]);
            } else if(empty($this->parts) ){
                $this->parent->replace($this, $replacement);
            }
        } else {
            $this->parts->add($replacement);
        }
    }
    
    public function nextValid($start){
        $next = -1;
        $t = 0;
        foreach ($this->parts as $part) {
            $t = $part->nextValid($start);
            if($t < 0){
                continue;
            }
            if($next == -1){
                $next = $t;
            } else {
                $next = min($next,$t);
            }
        }
        return next;
    }
}

class RangePart extends FieldPart implements PartParent {
    public $upper;
    public $lower;
    
    public function toCron(){
        return $this->lower->toCron() . "-" . $this->upper->toCron();
    }
    
    public function toString(){
        return $this->field->formatBetween($this, false);
    }
    
    public function toMarkup(){
        return $this->field->formatBetween($this, true);
    }
    
    public function setUpper($part){
        if($this->upper != null){
            $this->upper->parent = null;
            $this->upper->field = null; 
        }
        $this->upper = $part;
        $part->parent = $this;
        $part->field = $this->field ;
    }
    
    public function setLower($part){
        if($this->lower != null){
            $this->lower->parent = null;
            $this->lower->field = null; 
        }
        $this->lower = $part;
        $part->parent = $this;
        $part->field = $this->field ;
    }
    
    public function replace($target, $replacement){
        if($target == $this->lower){
            $this->setLower($replacement);
        } else if ($target == $this->upper){
            $this->setLower($replacement);
        }
    }
    
    public function relation($part){
        if($part == $this->lower){
            return "lower";
        }
        if($part == $this->upper){
            return "upper";
        }
        return null;
    }
    
    public function nextValid($start){
        if($start < $this->lower->getIntValue()){
            return $this->lower->getIntValue();
        }
        if($start > $this->upper->getIntValue()){
            return -$this->lower->getIntValue();
        }
        return $start;
    }
}

class WildcardFieldPart extends RangePart {
    public function __construct($field){
        $this->field = $field;
    }
    
    public function toString() {
        return $this->field->getWildcardText();
    }
    
    public function toMarkup() {
        return $this->markup("*");
    }
    
    public function getFullDescription(){
        return "";
    }
    
    public function toCron() {
//        $field = $this->field;
//        if(($field instanceof DayOfWeekField && $this == $field->value) || ($field instanceof DayOfMonthField && !() )
        return "*";
    }
    
    public function replace($target, $replacement){}
    
    public function nextValid($start){
        return $start;
    }
}

class IncrementPart extends FieldPart implements PartParent  {
    public $range;
    public $increment;
    
    public function toString(){
        if($this->range instanceof RangePart){
            return $this->field->formatIncrement($this->increment, false) . ($this->range instanceof WildcardFieldPart ? "" : $this->range->toString());
        } else {
            return "Doesn't make sense";
        }
    }
    
    public function toMarkup(){
        if($this->range instanceof RangePart){
            return $this->field->formatIncrement($this->increment, true) . $this->range->toMarkup();
        } else {
            return "Doesn't make sense";
        }
    }
    
    public function getFullDescription() {
        return $this->toString();
    }
    
    public function toCron() {
        return $this->range->toCron() . "/" . $this->increment->toCron();
    }
    
    public function setRange($range){
        if($this->range != null){
            $this->range->parent = null;
            $this->range->field = null;
        }
        $this->range = $range;
        $range->parent = $this;
        $range->field = $this->field;
    }
    
    public function setIncrement($increment){
        if($this->increment != null){
            $this->increment->parent = null;
            $this->increment->field = null;
        }
        $this->increment = $increment;
        $increment->parent = $this;
        $increment->field = $this->field;
    }
    
    public function replace($target, $replacement){
        if($target == $this->range){
            $this->setRange($replacement);
        } else if ($target == $this->increment){
            $this->setIncrement($replacement);
        }
    }
    
    public function relation($part){
        if($part == $this->range){
            return "range";
        }
        if($part == $this->increment){
            return "increment";
        }
    }
    
    public function nextValid($start){
        $s = $this->range->nextValid($start);
        $t = $this->range->nextValid(0);
        
        if($s < $start){
            return -$t;
        }
        
        if($s == $t){
            return $s;
        }
        
        
        $s = ceil(($s -$t)/$this->increment->getIntValue()) * $this->increment->getIntValue();
        
        return $t + $s;
    }
    
}

//$cron = new Cron();
//$cron->parse("*/5 1 */2 * * 2015-2020");
//echo $cron->toString(false) . "\n";
////echo $cron->toMarkup(false) . "\n";
//$next = $cron->nextValid();
//echo $next ."\n";
//for($i=0; $i < 10; $i++){
//    $d = new \DateTime($next);
//    $d->add(new \DateInterval("P1M"));
//    $next = $cron->nextValid($d) . "\n";
//    echo $next;
//}

}