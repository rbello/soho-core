```
        _            _            _       _    _       
       / /\         /\ \         / /\    / /\ /\ \     
      / /  \       /  \ \       / / /   / / //  \ \    
     / / /\ \__   / /\ \ \     / /_/   / / // /\ \ \   
    / / /\ \___\ / / /\ \ \   / /\ \__/ / // / /\ \ \  
    \ \ \ \/___// / /  \ \_\ / /\ \___\/ // / /  \ \_\ 
     \ \ \     / / /   / / // / /\/___/ // / /   / / / 
 _    \ \ \   / / /   / / // / /   / / // / /   / / /  
/_/\__/ / /  / / /___/ / // / /   / / // / /___/ / /   
\ \/___/ /  / / /____\/ // / /   / / // / /____\/ /    
 \_____\/   \/_________/ \/_/    \/_/ \/_________/     
                                                       
```

# soho-core
Core part of Soho Web Application Container

### Using API

There are several possibilities to access the API of the application.
First, the code executed on the server can use the API **direct access**:
```
$session = api('SessionsManager')->getSessionById(848037);
```

The **Command-Line Interface** allows you to run methods in text mode:
```
./go.sh cli session get-id 848037
```

Third-party applications based on the ENT data can do so thanks to the **REST interface**:
```
http://your_host/api/GestionSessions/getSessionById.json?id=848037
```

Finally, external applications based on a service-oriented approach can consume the services of the ENT via a **SOAP interface**.
```
http://your_host/api/GestionSessions.wsdl
```