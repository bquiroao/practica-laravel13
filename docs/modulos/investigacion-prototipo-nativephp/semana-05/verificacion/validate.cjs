const Parser=require(process.env.OPENAPI_PARSER_MODULE||'@apidevtools/swagger-parser');const path=require('node:path');const assert=require('node:assert/strict');
(async()=>{
 const api=await Parser.validate(path.resolve(__dirname,'../api/openapi.json'));
 console.log(`PASS OpenAPI ${api.openapi}: referencias y estructura validadas.`);
 const routes=Object.values(api.paths);assert.equal(routes.length,1);assert.deepEqual(Object.keys(routes[0]),['get']);
 const get=routes[0].get;assert.ok(api.security[0].sessionBearer);assert.deepEqual(Object.keys(get.responses).sort(),['200','400','401','403','404','429','503']);
 const s=get.responses['200'].content['application/json'].schema;assert.equal(s.additionalProperties,false);assert.deepEqual(s.properties.status.enum,['PENDIENTE','RECIBIDA','EN_PROCESO','COMPLETADA']);assert.deepEqual(Object.keys(s.properties).sort(),['demoOrderId','observedAt','requestId','source','status','tenantId']);
 assert.equal(get.responses['200'].headers['Cache-Control'].schema.const,'no-store');assert.ok(get.responses['429'].headers['Retry-After']);assert.ok(get.responses['503'].headers['Retry-After']);
 console.log('PASS límites: una ruta GET, sesión requerida, errores explícitos, estados del ejemplo, campos mínimos y no-store.');
 console.log('ALCANCE: validación estática; no ejecuta endpoint, autorización ni runtime NativePHP.');
})().catch(e=>{console.error(e.message);process.exit(1)});