# Modul zur Einbindung von Resol Solarreglern in IP-Symcon
[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Version](https://img.shields.io/badge/Symcon%20Version-5.0%20%3E-green.svg)

Folgende Module beinhaltet die Repository:

- ## Allgemein

Die Resol Solarregler senden sekündlich den Status der Sensoren und weitere Werte auf den "VBus".
Über einen LAN oder RS232 Adapter können diese Werte abgegriffen werden. 

Das Modul dient zum Empfang von Daten vom VBus. Dazu unterstütz das Modul die Resol Protokollversion 1.0.
Die Daten werden entsprechend ihrer Bedeutung aufbereitet und in IPS Variablen abgelegt.
Die Variablennamen können umbennant werden, müssen aber unter der Instanz bleiben.
Bekannte Formate bekommen automatisch ein Variablenprofil, alle anderen müssen manuell eines zugewiesen bekommen. 

VBus-Datensätze, der Resol Protokollversion 2.0, ermöglichen den Zugriff auf alle Werte, die über das Menüsystem des Moduls angepasst werden können. Dies ist __nicht__ in diesem Modul umgesetzt.

- ## Voraussetzung

IPS Version mindestens 5.2

- ## Installation

Nach hinzufügen des Moduls, kann einen neue Instanz angelegt werden.
Dazu nach "VBUS" oder "Resol" suchen.

![Instanz](docs/Instanz.png)

- ## Einstellungen

In der Instanz können folgende Einstellungen vorgenommen werden.
- __Gateway:__	Auswahl ob LAN (Netzwerk) oder RS232 (Seriell) Adapter
- __Sprache für Variablennamen:__ In der Beschreibung der Werte sind Deutsche und zum Teil Englische, Bezeichnungen. Je nach Auswahl werden die IPS Variablen entsprechend angelegt. Ein nachträgliches manuelles Ändern der Variablennamen ist natürlich jederzeit möglich.
- __Passwort:__ Der LAN Adapter sendet erst Daten, wenn über die Schnittstelle eine Passwort gesendet wird.  
	Standart: __vbus__  
	Dieses wird gesendet nach Übernehmen von Änderungen in der Konfiguration oder mit dem Button "Passwort Senden".  
	Gilt nur für den LAN Adapter. Für die RS232 Schnitstelle hat es keine Relevanz.

![Konfig](docs/Konfig.png)

- ## Funktionen

Resol_SendPass();

Mit der Funktion kann das Passwort manuell an den LAN Adapter gesendet werden.

- ## Changelog
| Version | Bemerkung    |
| ------- | ------------ |
 V1.0     | Grundversion |
 V1.1     | Variablenprofile anlegen , Überwachung auf komplette Datenstrings |
 V1.2     | fix: LAN Adapter Passwort, new: interner Datenbuffer |
 V1.3     | new: senden des Passwort auf Anforderung |
 V1.4     | fix: Delta Sol BX Plus
 V1.5     | new: Timer 
 V1.6     | fix: Passwort & Timer, unvollständige Daten erzeugen keine Fehlermeldung mehr
 V1.6.2   | new: automatisches senden  des PW nach TimeOut
 V1.7     | fix: Array and string offset access syntax with curly braces is deprecated in further PHP versions

