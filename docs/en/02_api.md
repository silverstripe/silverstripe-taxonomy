---
title: REST API
summary: Read and change terms and types as JSON from /admin/api/v1/taxonomy, and use curl to try each endpoint
icon: code
---

# REST API

The module serves a JSON API at `/admin/api/v1/taxonomy`. [`TaxonomyApiController`](api:SilverStripe\Taxonomy\Api\TaxonomyApiController) handles every request. The API lives under `/admin`, so the CMS admin middleware applies to it.

## Access

A request needs a logged-in member with access to the "Taxonomies" section of the CMS, or access to all CMS sections. The API also calls `canView()`, `canEdit()`, `canDelete()` and `canCreate()` on each record, on every request.

| Situation | Response |
| --------- | -------- |
| Not logged in | `401` |
| Logged in without access to the section | `403` |
| Record does not exist, or `canView()` fails for it | `404` |
| Collection endpoint, and `canView()` fails on the model | `403` |
| Any URL that is not listed below, or an ID that is not a positive whole number | `404` |
| Invalid `limit`, `offset`, `parentID` or `q` | `400` |
| Write request without a valid CSRF token | `400` |
| Write request with a body that is not `application/json` | `415` |
| Body that is not valid JSON, or not a JSON object | `400` |
| Record that the member can view but not change | `403` |
| Field that is missing, read-only or disabled, `Sort` that is not a valid position, or data that fails validation | `422` |

Errors always use the same JSON body:

```json
{
  "status": "error",
  "errors": [{"type": "error", "code": 404, "value": "The requested resource does not exist."}]
}
```

Validation failures use the same body with one error for each problem. An error for a field also has a `field` key:

```json
{
  "status": "error",
  "errors": [{"type": "error", "code": 422, "value": "\"Name\" is required", "field": "Name"}]
}
```

[`TaxonomyType`](api:SilverStripe\Taxonomy\TaxonomyType) allows `canView()` for every member with access to the section. Only administrators can edit, delete or create types.

An ID with a suffix, such as `12:move`, is not a valid ID. Every method returns `404` for it.

## Representation

A term looks like this. The attribute keys are the names of the data fields in the form of the record, so a client can send back what it reads.

```json
{
  "ID": 12,
  "Name": "Carrot",
  "ParentID": 7,
  "Sort": 2,
  "meta": {
    "className": "SilverStripe\\Taxonomy\\TaxonomyTerm",
    "typeName": "Plant",
    "ancestors": [{"ID": 1, "Name": "Plant"}, {"ID": 7, "Name": "Vegetable"}],
    "childCount": 0,
    "can": {"edit": true, "delete": true, "createChild": true}
  },
  "links": {
    "self": "admin/api/v1/taxonomy/terms/12",
    "children": "admin/api/v1/taxonomy/terms/12/children",
    "schema": "admin/api/v1/taxonomy/terms/12/schema"
  }
}
```

A record never contains the CSRF token. The `links` values are paths below the site root, without a leading slash. `TypeID` appears for top-level terms only, because child terms inherit their type. Send `null` as `TypeID` to give a term no type. `ParentID` is read-only. `Sort` is the 1-based position of the term among its siblings, and a `PATCH` request can change it (see [Reordering terms](#reordering-terms)). The `meta.typeName` value is empty when the member cannot view the type. A type has the same structure without `ParentID`, `Sort`, `ancestors`, `childCount`, `createChild` and `links.children`. A type has a `links.terms` link instead.

Lists use an envelope:

```json
{
  "items": [],
  "total": 0,
  "limit": 50,
  "offset": 0,
  "can": {"view": true, "create": false}
}
```

The `total` value counts the records the member can view. The `limit` parameter defaults to 50 and has a maximum of 200. The `offset` parameter defaults to 0.

## Endpoints

| Request | Description |
| ------- | ----------- |
| `GET /admin/api/v1/taxonomy` | Links to the collections with their `can` flags, and the CSRF token as `securityID` |
| `GET terms` | Top-level terms ordered by `Sort`. Use `q` to filter by part of the name, and `limit` and `offset` to page |
| `GET terms/{id}` | One term |
| `GET terms/{id}/children` | Direct children of a term ordered by `Sort`, with `limit` and `offset` |
| `GET terms/{id}/schema` | Form schema for editing the term |
| `GET terms/new/schema` | Form schema for a new top-level term. Needs `canCreate()` |
| `GET terms/new/schema?parentID={id}` | Form schema for a new child term, without `TypeID` when type inheritance is on |
| `GET types` | Types ordered by name, with `limit` and `offset` |
| `GET types/{id}` | One type |
| `GET types/{id}/terms` | Every term of the type at every depth, ordered by name, with `limit` and `offset` |
| `GET types/{id}/schema` | Form schema for editing the type |
| `GET types/new/schema` | Form schema for a new type. Needs `canCreate()` |
| `GET terms/{id}/field/{name}/{action}` | Request that a field of the term form makes, e.g. `TypeID/search` |
| `GET terms/new/field/{name}/{action}` | The same for a new term. Accepts `parentID` like `terms/new/schema` |
| `POST terms` | Create a top-level term. Needs `canCreate()`. Returns `201` with a `Location` header |
| `POST terms/{id}/children` | Create a child of the term. The parent comes from the URL |
| `PATCH terms/{id}` | Change the fields in the body and leave the others. A `Sort` key moves the term among its siblings. Needs `canEdit()` |
| `DELETE terms/{id}` | Delete the term and all nested terms. Needs `canDelete()`. Returns `204` |
| `POST types` | Create a type. Needs `canCreate()` |
| `PATCH types/{id}` | Change the fields in the body and leave the others. Needs `canEdit()` |
| `DELETE types/{id}` | Delete the type. Needs `canDelete()`. Returns `204` |

A schema request returns the schema part by default. Send the `X-Formschema-Request` header with `schema`, `state` or `schema,state` to choose the parts.

The `parentID` parameter must be a positive whole number. The parent must exist and pass `canView()`. The member must also pass `canEdit()` on the parent and `canCreate()` with the parent as context. Otherwise the response is `400`, `404` or `403`.

## Writing records

Every request other than `GET` needs the CSRF token. Send it in the `X-SecurityID` header, in the `SecurityID` request variable, or as a `SecurityID` key in the JSON body. The API root returns the token as `securityID`. A missing or wrong token gives `400`.

A write request with a body needs the header `Content-Type: application/json`, and the body must be a JSON object. The `PATCH` and `POST` routes all work this way. A `DELETE` request has no body.

The API builds the form of the record for the current member. It accepts only keys that are writable data fields in that form. A key that is not a field of the form, or is read-only or disabled, gives `422` with an error for that key, and nothing is written. This covers `ID`, `ParentID`, `meta`, `links` and `ClassName`, and `Sort` on every request except `PATCH terms/{id}`. The `SecurityID` key is the only exception. A body may carry it, and the API removes it before it checks the other keys.

For `PATCH`, the API loads the current values into the form, merges the keys of the body over them, validates the whole form and then saves the record. Keys that are not in the body keep their value. The write runs in a database transaction.

Terms and types need a `Name`. The `Name` value must not be empty or only white space.

`POST terms/{id}/children` loads the parent from the URL. The member must be able to view and edit the parent, and `canCreate()` on the model receives the parent in its `$context` argument as `['Parent' => $parent]`. The body cannot set `ParentID`. A child term has no `TypeID` field when type inheritance is on, and takes the type of its parent.

A new term goes after its siblings, so its position is one more than the number of its siblings.

### Reordering terms {#reordering-terms}

`Sort` is the 1-based position of a term among the terms with the same parent. A list returns terms ordered by `Sort`, and a term shows its position as `Sort`. The position is not the raw database value, which can have gaps or zeros in old data. A client can send back the `Sort` that it reads.

`PATCH terms/{id}` takes a body like `{"Sort": 2}`. The API renumbers every sibling from 1 to the sibling count, and returns the moved term. `Sort` can be the only key, or it can come with other form fields. The API saves the form fields and the new order in one transaction.

The response is `422` with an error on the `Sort` field when `Sort` is not a whole JSON number, is less than 1, or is greater than the number of siblings. Nothing is written in that case. The API never clamps the value.

The API renumbers the siblings with a database update and does not save them as records, so a reorder does not write the descendants of the terms. For a child term the member also needs `canEdit()` on the parent. A term cannot move to another parent, because `ParentID` is read-only.

### Field requests {#field-requests}

Some fields make their own requests. With more than `DBForeignKey.dropdown_field_threshold` types, the `TypeID` field loads its options from the server, and the form schema holds a flat link for it:

```text
/admin/api/v1/taxonomy/terms/12/field/TypeID/search?term=Bio
/admin/api/v1/taxonomy/terms/new/field/TypeID/search?term=Bio
```

The API builds the form for the current member, finds the field by name and passes the request to it. The request needs the CSRF token in the `X-SecurityID` header or the `SecurityID` query parameter, because the field checks it even for `GET`. A field that does not exist gives `404`. A read-only or disabled field gives `403`. The API removes records that the member cannot view from the results.

## Unsupported form fields {#unsupported-form-fields}

If the form of a record has a `GridField`, the API throws a `LogicException` that names the class and field. A `GridField` needs a nested request handler, which the API does not provide.

## Try the API with curl

Log in once to get a session cookie. The login form needs its own CSRF token.

```bash
curl -s -c cookies.txt -o login.html https://example.com/Security/login
SECURITY_ID=$(grep -o 'name="SecurityID" value="[^"]*"' login.html | head -n 1 | sed 's/.*value="//;s/"//')
curl -s -b cookies.txt -c cookies.txt -o /dev/null \
  --data-urlencode "Email=admin@example.com" \
  --data-urlencode "Password=secret" \
  --data-urlencode "SecurityID=$SECURITY_ID" \
  --data-urlencode "action_doLogin=Log in" \
  https://example.com/Security/login/default/LoginForm
```

A site with the `silverstripe/mfa` module redirects to an extra step after the login form. When the member has no MFA method and the site lets members skip the registration, this request finishes the login:

```bash
curl -s -b cookies.txt -c cookies.txt -o /dev/null https://example.com/Security/login/default/mfa/skip
```

A member with a registered MFA method has to complete the verification of that method instead.

Read the API root, which includes the CSRF token for later write requests:

```bash
curl -s -b cookies.txt https://example.com/admin/api/v1/taxonomy
```

List the top-level terms, search by name and page through the results:

```bash
curl -s -b cookies.txt "https://example.com/admin/api/v1/taxonomy/terms"
curl -s -b cookies.txt "https://example.com/admin/api/v1/taxonomy/terms?q=plant"
curl -s -b cookies.txt "https://example.com/admin/api/v1/taxonomy/terms?limit=10&offset=10"
```

Read one term and its children:

```bash
curl -s -b cookies.txt https://example.com/admin/api/v1/taxonomy/terms/12
curl -s -b cookies.txt https://example.com/admin/api/v1/taxonomy/terms/12/children
```

Read the types:

```bash
curl -s -b cookies.txt https://example.com/admin/api/v1/taxonomy/types
curl -s -b cookies.txt https://example.com/admin/api/v1/taxonomy/types/3
curl -s -b cookies.txt https://example.com/admin/api/v1/taxonomy/types/3/terms
```

Read the form schema and state of a term, and the schema for new records:

```bash
curl -s -b cookies.txt -H "X-Formschema-Request: schema,state" https://example.com/admin/api/v1/taxonomy/terms/12/schema
curl -s -b cookies.txt https://example.com/admin/api/v1/taxonomy/terms/new/schema
curl -s -b cookies.txt "https://example.com/admin/api/v1/taxonomy/terms/new/schema?parentID=7"
curl -s -b cookies.txt https://example.com/admin/api/v1/taxonomy/types/3/schema
curl -s -b cookies.txt https://example.com/admin/api/v1/taxonomy/types/new/schema
```

## Change records with curl

The requests below use the cookie file from the login step. Read the CSRF token from the API root once, and send it in the `X-SecurityID` header.

```bash
TOKEN=$(curl -s -b cookies.txt https://example.com/admin/api/v1/taxonomy | grep -o '"securityID":"[^"]*"' | sed 's/.*:"//;s/"//')
```

Create a type, then a top-level term and a child term:

```bash
curl -s -b cookies.txt -X POST -H "X-SecurityID: $TOKEN" -H "Content-Type: application/json" \
  -d '{"Name": "Biology"}' https://example.com/admin/api/v1/taxonomy/types
curl -s -b cookies.txt -X POST -H "X-SecurityID: $TOKEN" -H "Content-Type: application/json" \
  -d '{"Name": "Plant", "TypeID": 3}' https://example.com/admin/api/v1/taxonomy/terms
curl -s -b cookies.txt -X POST -H "X-SecurityID: $TOKEN" -H "Content-Type: application/json" \
  -d '{"Name": "Vegetable"}' https://example.com/admin/api/v1/taxonomy/terms/12/children
```

Change the name of a term and leave its other fields as they are:

```bash
curl -s -b cookies.txt -X PATCH -H "X-SecurityID: $TOKEN" -H "Content-Type: application/json" \
  -d '{"Name": "Flora"}' https://example.com/admin/api/v1/taxonomy/terms/12
curl -s -b cookies.txt -X PATCH -H "X-SecurityID: $TOKEN" -H "Content-Type: application/json" \
  -d '{"Name": "Science"}' https://example.com/admin/api/v1/taxonomy/types/3
```

Move a term to the first position among its siblings:

```bash
curl -s -b cookies.txt -X PATCH -H "X-SecurityID: $TOKEN" -H "Content-Type: application/json" \
  -d '{"Sort": 1}' https://example.com/admin/api/v1/taxonomy/terms/12
```

Search the types that the `TypeID` field offers. This request needs the token too:

```bash
curl -s -b cookies.txt -H "X-SecurityID: $TOKEN" "https://example.com/admin/api/v1/taxonomy/terms/12/field/TypeID/search?term=Bio"
curl -s -b cookies.txt -H "X-SecurityID: $TOKEN" "https://example.com/admin/api/v1/taxonomy/terms/new/field/TypeID/search?term=Bio"
```

Delete a term with all its children, and delete a type:

```bash
curl -s -b cookies.txt -X DELETE -H "X-SecurityID: $TOKEN" https://example.com/admin/api/v1/taxonomy/terms/12
curl -s -b cookies.txt -X DELETE -H "X-SecurityID: $TOKEN" https://example.com/admin/api/v1/taxonomy/types/3
```
