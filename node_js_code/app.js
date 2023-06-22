const https = require('https');
const fs = require('fs');
const sleep = require('sleep-promise');
const { Resolver } = require('dns');
const { produceFundsMQTT } = require('./mqtt-producer');
let apiKey = null;
const url = 'https://api.ewmarkit.com';
const host = 'api.ewmarkit.com';
const project = 'inavplus';
const username = 'markit/resellers/Fidante/accounts/restapi.prod';
const password = 'nb25@1kHvxTr';
const INVALID_API_KEY_MESSAGE = 'INVALID_API_KEY';
const LIMIT_EXCEEDED_MESSAGE = 'APIKEY_LIMIT_EXCEEDED';

// A function to construct the endpoint
const constructMainAPIURL = (entity = 'Fund', service = 'latest', isAPIkeyCall = false) => {
    if(!isAPIkeyCall) {
        return `${url}/${project}/${entity}/${service}`;
    } else {
        return `${url}/${entity}`;
    }
};

// A function to contruct the endpoint path (without the domain)
const constructMainAPIPath = (entity = 'Fund', service = 'latest', isAPIkeyCall = FALSE) => {
    if(!isAPIkeyCall) {
        return `/${project}/${entity}/${service}`;
    } else {
        return `/${entity}`;
    }
};

// A function to get the API key from the ewmarkitAPI 
const getIHSMAPIKey = () => {
    return new Promise((resolve, reject) => {
        let apiPath = constructMainAPIPath('apikey', '', true);
        apiPath = `${apiPath}?username=${username}&password=${password}&format=json`;
    
        const postData = JSON.stringify({
            'username' : username,
            'password' : password,
            'format' : 'json',
        });
        const options = {
            hostname: host,
            port: 443,
            path: apiPath,
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
              }
           
          };
    
        var req = https.request(options, (res) => {
            console.log('statusCode from APIKEY call:', res.statusCode);
            res.on('data', (d) => {
                console.log(d.toString());
                if(res.statusCode === 200) {
                    resolve(d);
                }
                
            });        
    
        })
        req.on('error', (e) => {
            console.error('EROOROOOROOROOR......');
            console.log(e);
        });
        req.write(postData);
        req.end();
        
    });

    
}

// A function to write the api key to the file.
const writeAPIKeytoFile = (str) => {
    return new Promise((resolve, reject) => {
        fs.writeFile('./files/auth.txt', str, () => {
            console.log('APIKEY written to file');
            resolve(str);
        });
    });
    
};


// a function to get the api key from the file
const getAPIKey = () => {
    return new Promise((resolve, reject) => {
        fs.readFile('./files/auth.txt', (err, data) => {
            if(err) {
                console.log('There was an ERRRRRROOORRR while reading the api key from the file')
            } else {
                console.log('reading api key from file');
                
            }
            apiKey = data.toString();
            console.log(apiKey);
            resolve(apiKey); 
        });
    });
};


const getAllFundDatafromewmarkit = async () => {
    return new Promise((resolve, reject) => {
        // reading the variable to check if null
        if(apiKey !== null) {
            // if the file value is NOT empty
            if(apiKey !== '') {
                let url = constructMainAPIURL();
                url = `${url}?apikey=${apiKey}&format=json`;
                var res = https.get(url, (res) => {
                    let data = '';

                    res.on('data', chunk =>{
                        data += chunk;
                    });

                    res.on('end', () => {
                        console.log('statusCode from fundata call:', res.statusCode);
                        if(res.statusCode === 400) {

                            let jsonData = JSON.parse(data);
                            if(jsonData.errorCode === INVALID_API_KEY_MESSAGE || jsonData.errorCode === LIMIT_EXCEEDED_MESSAGE) {
                                 sleep(1000)
                                 .then(getIHSMAPIKey)
                                .then(writeAPIKeytoFile)
                                .then(getAPIKey)
                                .then(getAllFundDatafromewmarkit);
                            }
                        }
                        
                        if(res.statusCode == 200) {
                            resolve(data);
                        }
                        
                    })
                })
                .on('error', err => {
                    console.log('Error : ' + err.message)
                });
            } else {
                 getIHSMAPIKey()
                .then(writeAPIKeytoFile)
                .then(getAPIKey)
                .then(getAllFundDatafromewmarkit);
            }
            
            
            
        } else {
            //If null read from the file.
             getAPIKey()
            .then(getAllFundDatafromewmarkit);
        }
    });
    
}


console.log('started');
const timer = setInterval( async () => {
    getAllFundDatafromewmarkit()
    .then(produceFundsMQTT);
}, 1000);



