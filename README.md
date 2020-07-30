# Modul zur Einbindung von Resol Solarreglern in IP-Symcon
[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Version](https://img.shields.io/badge/Symcon%20Version-4.2%20%3E-green.svg)

Folgende Module beinhaltet die Repository:

- __VBUS__ ([Dokumentation](VBUS))  

	Die Resol Solarregler senden sekündlich den Status der Sensoren und weitere Werte auf den "VBUS".
	Über einen LAN oder RS232 Adapter können diese Werte abgegriffen werden. 
	
	Das Modul dient zum Empfang von Daten über den "V-Bus". Dazu unterstütz das Modul die Resol Protokollversion 1.0
	Die Daten werden entsprechend ihrer Bedeutung aufbereitet und in IPS Variablen abgelegt.
	Die Variablennamen können umbennant werden, müssen aber unter der Instanz bleiben.
	Bekannte Formate bekommen automatisch ein Variablenprofil, alle anderen müssen manuell eines zugewiesen bekommen. 

	VBus-Datenströme der Protokollversion 2.0 (kurz „Datagramme“ genannt) ermöglichen den Zugriff auf alle Werte, die über das Menüsystem des Moduls angepasst werden können. Dies ist __nicht__ in diesem Modul umgesetzt.

- __Voraussetzung__

IPS Version mindestens 4.2

- __Installation__

Nach hinzufügen des Moduls, kann einen neue Instanz angelegt werden.
Dazu nach "VBUS" oder "Resol" suchen.

![Instanz](docs/Instanz.png)

- __Einstellungen__

In der Instanz können folgende Einstellungen vorgenommen werden.
- __Gateway:__	Auswahl ob LAN (Netzwerk) oder RS232 (Seriell) Adapter
- __Sprache für Variablennamen:__ In der Beschreibung der Werte sind Deutsche und zum Teil Englische, Bezeichnungen. Je nach Auswahl werden die IPS Variablen entsprechend angelegt. Ein nachträgliches manuells Ändern der Variablennamen ist natürlich jederzeit möglich.
- __Passwort:__ Der LAN Adapter sendet erst Daten, wenn über die Schnittstelle eine Passwort gesendet wird.  
	Standart: __vbus__  
	Dieses wird gesendet nach Übernehmen von Änderungen in der Konfiguration oder mit dem Button "Passwort Senden".  
	Gilt nur für den LAN Adapter. Für die RS232 Schnitstelle hat es keine Relevanz.

![Konfig](docs/Konfig.png)

- __Funktionen__

Resol_SendPass();

Mit der Funktion kann das Passwort manuell an den LAN Adapter gesendet werden.

- __Changelog__
1. | V1.0 | Grundversion
