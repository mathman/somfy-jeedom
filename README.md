# Plugin somfy-jeedom
Plugin Jeedom pour communiquer avec les box Somfy (Tahoma, connexoon, etc...)

Le plugin utilise un démon en nodeJs.
Ce démon permet:
- De gerer les équipements Somfy via l'API [Somfy Open API](https://developer.somfy.com/)
- De lancer un serveur web (express) pour pouvoir accéder aux données via des requetes http.

Une gestion des dépendances permet d'installer tous les packages nécessaire au fonctionnement du plugin.

Ce plugin fonctionne avec les volets roulants Somfy.
