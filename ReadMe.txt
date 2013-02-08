
 Read Me:
 ------------------
- APIs/
	Hier befinden sich alle Semantic API-Schnittstellen:
	- Zemanta API f�r die Keyword Extraktion
	- ARC2 f�r den Triple Store  

- BrowserPlugin/
	Hier befinden sich alle relevanten Files f�r das Browser Plugin. Dieser Ordner muss im Google Chrome Browser als Plugin-Ordner hochgeladen werden. 

- conig/ 
	Hier befinden sich alle Hauptkonfigurationen des Projektes 
	(Backup des DB-Dump, DBpedia DB-Dump, config.xml mit den Zugangsdaten, API Keys und NewsFeed Links)

- Project/
	Hier befinden sich alle PHP-Klassen
	- Database.php: Datenbank Klasse, die f�r die Select + Inserts zust�ndig ist
	- DBpediaDatabase.php: Zust�ndig f�r die Kommunikation mit DBpedia 
		(Informationen �ber Keywords, Kategorien, �hnlichkeitsberechnung)
	- MainController.php: Zust�ndig f�r die Kommunikation zwischen Frontend, Browser Plugin, Model
		Leitet die Requests und Response zu den zust�ndigen Schnittstellen weiter
	- processAction.php: Leitet Requests von Frontend und BrowserPlugin an den MainController, Session-Handling
	- TermItem.php: Model Klasse f�r die Zwischenspeicherung der Begriffe

