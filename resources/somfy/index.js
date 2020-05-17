const express = require('express');
const bodyParser = require('body-parser');
const npid = require('npid');
const Somfy = require('./lib/somfy');

const args = process.argv.slice(2);
const port = args[0];
const consumeKey = args[1];
const secret = args[2];
const ip_address = args[3];

let webServer;
let somfy;

(async () => {
    
    try {
        var pid = npid.create(args[4]);
        pid.removeOnExit();
    } catch (err) {
        console.log(err);
        process.exit(1);
    }
    
    const app = express();
    
    app.use(bodyParser.json());
    app.use(bodyParser.urlencoded({ extended: true }));
    
    somfy = new Somfy();

    somfy.init(consumeKey, secret, 'http://' + ip_address + ':' + port + '/redirect');

    // Initial page redirecting to somfy api
    app.get('/auth', (req, res) => {
        
        console.log(somfy.getAuthorizationUri());
        res.redirect(somfy.getAuthorizationUri());
    })
    // Callback service parsing the authorization token and asking for the access token
    .get('/redirect', async (req, res) => {
        
        const { code } = req.query;
        let response = await somfy.createToken(code);
        if('access_token' in response) {
            
            res.status(200);
            res.send(response);
        }
        else {
            
            res.status(500);
            res.send(response);
        }
    })
    .get('/', (req, res) => {
        
        res.send('Hello<br><a href="/auth">Log in with Somfy API</a>');
    })
    .get('/allsites', async (req, res) => {
        
        let sites = await somfy.getALLSites();
        if (typeof sites !== 'undefined' && 'data' in sites) {
            
            res.setHeader('Content-Type', 'application/json');
            res.end(JSON.stringify(sites.data));
        }
        else {
            
            res.status(504);
            res.send('Error');
        }
    })
    .get('/site/:siteid', async (req, res) => {
        
        let site = await somfy.getSite(req.params.siteid);
        if (typeof site !== 'undefined' && 'data' in site) {
            
            res.setHeader('Content-Type', 'application/json');
            res.end(JSON.stringify(site.data));
        }
        else {
            
            res.status(504);
            res.send('Error');
        }
    })
    .get('/site/:siteid/device', async (req, res) => {
        
        let devices = await somfy.getDevicesFromSiteId(req.params.siteid);
        if (typeof devices !== 'undefined' && 'data' in devices) {
            
            res.setHeader('Content-Type', 'application/json');
            res.end(JSON.stringify(devices.data));
        }
        else {
            
            res.status(504);
            res.send('Error');
        }
    })
    .get('/device/:deviceid', async (req, res) => {
        
        let device = await somfy.getDevice(req.params.deviceid);
        if (typeof device !== 'undefined' && 'data' in device) {
            
            res.setHeader('Content-Type', 'application/json');
            res.end(JSON.stringify(device.data));
        }
        else {
            
            res.status(504);
            res.send('Error');
        }
    })
    .post('/device/:deviceid/exec', async function(req, res) {

        let jobId = await somfy.setCommand(req.params.deviceid, req.body);
		if (typeof jobId !== 'undefined' && 'data' in jobId) {
            
            res.setHeader('Content-Type', 'application/json');
            res.end(JSON.stringify(jobId.data));
        }
        else {
            
            res.status(504);
            res.send('Error');
        }
    })
    .get('/stop', function(req, res) {
        process.exit(0);
    })
    .use(function(req, res, next){
        res.setHeader('Content-Type', 'text/plain');
        res.status(404).send('Page introuvable !');
    });
    
    webServer = app.listen(port, function () {
        
        console.log("Api started on port " + port);
        console.log("Program started");
    });
})();

process.on("SIGINT", async () => {
    
    if (webServer) {
        
        webServer.close(() => {
            
            console.log('Http server closed.');
        });
    }
    process.removeAllListeners("SIGINT");
});