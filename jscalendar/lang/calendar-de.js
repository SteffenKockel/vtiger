// Author: Hartwig Weinkauf h_weinkauf@gmx.de
// �erarbeitet und fehlende Texte hinzugefgt von Gerhard Neinert (gerhard at neinert punkt de)
// Feel free to use / redistribute under the GNU LGPL.
// ** I18N



// full day names
Calendar._DN = new Array
("Sonntag",
 "Montag",
 "Dienstag",
 "Mittwoch",
 "Donnerstag",
 "Freitag",
 "Samstag",
 "Sonntag");
 
// short day names
Calendar._SDN = new Array
("So",
 "Mo",
 "Di",
 "Mi",
 "Do",
 "Fr",
 "Sa",
 "So"); 

// short day names only use 2 letters instead of 3
Calendar._SDN_len = 2;

// full month names
Calendar._MN = new Array
("Januar",
 "Februar",
 "M�rz",
// "M\u00e4rz",
 "April",
 "Mai",
 "Juni",
 "Juli",
 "August",
 "September",
 "Oktober",
 "November",
 "Dezember");

// short month names
Calendar._SMN = new Array
("Jan",
 "Feb",
 "M�r",
// "M\u00e4r",
 "Apr",
 "Mai",
 "Jun",
 "Jul",
 "Aug",
 "Sep",
 "Okt",
 "Nov",
 "Dez");

// tooltips
Calendar._TT = {};
Calendar._TT["INFO"] = "�ber den Kalender";

Calendar._TT["ABOUT"] =
"DHTML Date/Time Selector\n" +
"(c) dynarch.com 2002-2003\n" + // don't translate this this ;-)
"For latest version visit: http://dynarch.com/mishoo/calendar.epl\n" +
"Distributed under GNU LGPL.  See http://gnu.org/licenses/lgpl.html for details." +
"\n\n" +
"Datumsauswahl:\n" +
"- Benutze die \xab, \xbb Buttons um das Jahr auszuw�hlen\n" +
"- Benutze die " + String.fromCharCode(0x2039) + ", " + String.fromCharCode(0x203a) + " Buttons um einen Monat auszuw�hlen\n" +
"- F�r eine schnellere Auswahl, halte die Maus �ber einen dieser Buttons gedr�ckt.";

Calendar._TT["ABOUT_TIME"] = "\n\n" +
"Time selection:\n" +
"- Click on any of the time parts to increase it\n" +
"- or Shift-click to decrease it\n" +
"- or click and drag for faster selection.";

Calendar._TT["PREV_YEAR"] = "Vorheriges Jahr (halte f�r Men�)";
Calendar._TT["PREV_MONTH"] = "Vorheriger Monat (halte f�r Men�)";
Calendar._TT["GO_TODAY"] = "Heute";
Calendar._TT["NEXT_MONTH"] = "N�chster Monat (halte f�r Men�)";
Calendar._TT["NEXT_YEAR"] = "N�chstes Jahr (halte f�r Men�)";
Calendar._TT["SEL_DATE"] = "Datum ausw�hlen";
Calendar._TT["DRAG_TO_MOVE"] = "Zum Verschieben festhalten";
Calendar._TT["PART_TODAY"] = " (heute)";

// the following is to inform that "%s" is to be the first day of week
// %s will be replaced with the day name.
Calendar._TT["DAY_FIRST"] = "%s zuerst anzeigen";

// This may be locale-dependent.  It specifies the week-end days, as an array
// of comma-separated numbers.  The numbers are from 0 to 6: 0 means Sunday, 1
// means Monday, etc.
Calendar._TT["WEEKEND"] = "0,6";

Calendar._TT["CLOSE"] = "Schliessen";
Calendar._TT["TODAY"] = "Heute";
Calendar._TT["TIME_PART"] = "(Shift-)Klick oder festhalten um Wert zu �ndern";

// date formats
Calendar._TT["DEF_DATE_FORMAT"] = "%Y-%m-%d";
Calendar._TT["TT_DATE_FORMAT"] = "%a, %b %e";

Calendar._TT["WK"] = "wk";
Calendar._TT["TIME"] = "Zeit:";
