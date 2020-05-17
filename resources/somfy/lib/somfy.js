const oauthModule = require('simple-oauth2');
const axios = require('axios');
const fs = require('fs');

class Somfy {

    constructor() {
        this.token = null;
        this.oauth2 = null;
        this.authorizationUri = null;
        this.redirectUrl = null;
        this.baseUrl = 'https://api.somfy.com/api/v1';
    }
    
    async init(clientId, secret, redirectUrl) {
        
        this.oauth2 = oauthModule.create({
        
            client: {
                id: clientId,
                secret: secret
            },
            auth: {
                tokenHost: 'https://accounts.somfy.com',
                tokenPath: '/oauth/oauth/v2/token',
                authorizePath: '/oauth/oauth/v2/auth'
            },
            options: {
                authorizationMethod: 'body',
                bodyFormat: 'json'
            }
        });
        this.authorizationUri = this.oauth2.authorizationCode.authorizeURL({
        
            redirect_uri: redirectUrl
        });
        this.redirectUrl = redirectUrl;
    }

    getAuthorizationUri() {
        
        return this.authorizationUri;
    }
    
    async createToken(code) {
        
        if (code !== null) {
                    
            const options = {
                code,
                redirect_uri: this.redirectUrl,
            };
            try {
                        
                const result = await this.oauth2.authorizationCode.getToken(options);
                this.token = this.oauth2.accessToken.create(result);
                fs.writeFileSync(__dirname + '/../token.json', JSON.stringify(this.token.token));
                return this.token.token;
            } catch (error) {
                        
                console.error('Access Token Error', error.message);
                return error.message;
            }
        }
        else {
                   
            console.error('No code authorization!');
            return 'No code authorization!';
        }
    }
    
    async updateToken() {
        
        if (this.token !== null && !this.token.expired()) {
            
            return this.token.token;
        }
        else if (this.token !== null) {
            
            try {
                this.token = await this.token.refresh();
                fs.writeFileSync(__dirname + '/../token.json', JSON.stringify(this.token.token));
                return this.token.token;
            } catch (error) {
                
                console.log(error.message);
                console.log('Need authorization request!');
                return error.message;
            }
        }
        else {
            
            try {
                
                let data = fs.readFileSync(__dirname + '/../token.json');
                console.log('File token exist');
                this.token = this.oauth2.accessToken.create(JSON.parse(data.toString()));
                if (this.token.expired()) {
                    try {
                        
                        this.token = await this.token.refresh();
                        fs.writeFileSync(__dirname + '/../token.json', JSON.stringify(this.token.token));
                    } catch (error) {

                        console.log(error.message);
                        console.log('Need authorization request!');
                        return error.message;
                    }
                }
                console.log('Return token');
                return this.token.token;
            } catch (error) {
                
                console.log(error.message);
                console.log('Need authorization request!');
                return error.message;
            }
        }
    }
    
    async getALLSites() {
        
        let token = await this.updateToken();
        const options = {
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token.access_token,
            }
        };
        try {
            
            let data = await axios.get(this.baseUrl + '/site', options);
            return data;
        } catch (error) {
            
            console.log(error.message);
        }
    }
    
    async getSite(siteId) {
        
        let token = await this.updateToken();
        const options = {
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token.access_token,
            }
        };
        try {
            
            let data = await axios.get(this.baseUrl + '/site/' + siteId, options);
            return data;
        } catch (error) {
            
            console.log(error.message);
        }
    }
    
    async getDevicesFromSiteId(siteId) {
        
        let token = await this.updateToken();
        const options = {
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token.access_token,
            }
        };
        try {
            
            let data = await axios.get(this.baseUrl + '/site/' + siteId + '/device', options);
            return data;
        } catch (error) {
            
            console.log(error.message);
        }
    }
    
    async getDevice(deviceId) {
        
        let token = await this.updateToken();
        const options = {
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token.access_token,
            }
        };
        try {
            
            let data = await axios.get(this.baseUrl + '/device/' + deviceId, options);
            return data;
        } catch (error) {
            
            console.log(error.message);
        }
    }
    
    async setCommand(deviceId, command) {
        
        let token = await this.updateToken();
        const options = {
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token.access_token,
            }
        };
        let postData = {
            "name": command.nameCommand,
            "parameters": []
        }
        if ('nameParameter' in command && 'valueParameter' in command) {
            
            postData.parameters.push({"name": command.nameParameter, "value": command.valueParameter});
        }
        try {
            
            let data = await axios.post(this.baseUrl + '/device/' + deviceId + '/exec', postData, options);
            return data;
        } catch (error) {
            
            console.log(error.message);
        }
    }
}

module.exports = Somfy;