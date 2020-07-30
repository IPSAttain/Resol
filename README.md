# Modul zur Einbindung von Resol Solarreglern in IP-Symcon

Folgende Module beinhaltet die Repository:

- __VBUS__ ([Dokumentation](VBUS))  
	Die Resol Solarregler senden sekündlich den Status der Sensoren und weitere Werte auf den "VBUS".
	Über einen LAN oder RS232 Adapter können diese Werte abgegriffen werden. 

	Das Modul dient zum Empfang von Daten über den "V-Bus".
	Die Daten werden entsprechend ihrer Bedeutung aufbereitet und in IPS Variablen abgelegt.
	Die Variablennamen können umbennant werden, müssen aber unter der Instanz bleiben.
	Bekannte Formate bekommen automatisch ein Variablenprofil, alle anderen müssen manuell eines zugewiesen bekommen. 

- __Voraussetzung__

IPS Version mindestens 4.2

- __Installation__

Nach hinzufügen des Moduls, kann einen neue Instanz angelegt werden.
Dazu kann nach "VBUS" oder "Resol" gesucht werden.

![Instanz](docs/Instanz.PNG?raw=true "Instanz")


- __Changelog__
1. | V1.0 | Grundversion