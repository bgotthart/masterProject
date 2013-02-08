
 Read Me:
 ------------------
- APIs/
	Hier befinden sich alle Semantic API-Schnittstellen:
	- Zemanta API für die Keyword Extraktion
	- ARC2 für den Triple Store  

- BrowserPlugin/
	Hier befinden sich alle relevanten Files für das Browser Plugin. Dieser Ordner muss im Google Chrome Browser als Plugin-Ordner hochgeladen werden. 

- conig/ 
	Hier befinden sich alle Hauptkonfigurationen des Projektes 
	(Backup des DB-Dump, DBpedia DB-Dump, config.xml mit den Zugangsdaten, API Keys und NewsFeed Links)

- Project/
	Hier befinden sich alle PHP-Klassen
	- Database.php: Datenbank Klasse, die für die Select + Inserts zuständig ist
	- DBpediaDatabase.php: Zuständig für die Kommunikation mit DBpedia 
		(Informationen über Keywords, Kategorien, Ähnlichkeitsberechnung)
	- MainController.php: Zuständig für die Kommunikation zwischen Frontend, Browser Plugin, Model
		Leitet die Requests und Response zu den zuständigen Schnittstellen weiter
	- processAction.php: Leitet Requests von Frontend und BrowserPlugin an den MainController, Session-Handling
	- TermItem.php: Model Klasse für die Zwischenspeicherung der Begriffe

