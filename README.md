# ZenPCL
Zensquare PHP Cron Library

ZenPCL is a port of ZenJCL written in PHP. It does not contain the gui features of
ZenJCL.

What ZenJCL does
---------------------
 - Parse Cron style expression strings
 - Evaluates when the expression will next be true
 - Can be used as a Code Igniter library
 - Allows for super simple integration and with no dependencies
 - Display Cron style expressions in a human readable form (English only)

ZenJCL does not
----------------------
 - Provide any kind of GUI
 - Do anything but parse expressions and evaluate 

Hello World!
----------------------

```
$cron = new Cron();
$cron->parse("*/5 1 */2 * * 2015-2020"); //Parse an expression
echo $cron->toString(false) . "\n"; //Print it in "English"
for($i = 0; $i < 5; $i++){ //Get the next 5 valid dates
    echo $cron->nextValid()->toDateTime(); //Get the next valid date and print it as YYYY-MM-DD HH:mm:SS
}
```

As a code igniter library
```
$this->load->library('cron');
$this->cron->parse("*/5 1 */2 * * 2015-2020"); //Parse an expression
echo $this->cron->toString(false) . "\n"; //Print it in "English"
for($i = 0; $i < 5; $i++){ //Get the next 5 valid dates
    echo $this->cron->nextValid()->toDateTime(); //Get the next valid date and print it as YYYY-MM-DD HH:mm:SS
}
```


Core Methods
-----------------------
###parse(String expression)

###nextValid(DateTime/String/Null date)
 - if Null - on first call new DateTime(), on second call value of last call
 - if String - new DateTime(expression) - read datetime documentation
 - if DateTime - next valid after the given date time

Human Readable
-----------------------
I'm not going to say it's beautiful - but for many less technical people it will
make understanding a Cron expression easier. Here are some examples of the output
of the Schedule class.
```
"0 11 * * 1-6/2 2015" - on the 1st minute of the hour at 11am every 2nd day from Monday to Saturday in 2015
"*/5 * * * 1-5 *" - every 5th minute from Monday to Friday
"1 1 2 * * *" - on the 2nd minute of the hour at 1am on the 2nd day of the month
```
