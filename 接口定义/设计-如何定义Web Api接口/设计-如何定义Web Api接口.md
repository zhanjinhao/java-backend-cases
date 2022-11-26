## 设计-如何定义Web Api接口

### request

#### restful

每当提到如何定义Web Api接口（后续简称接口）时，首先想到的就是restful风格。而restful风格指的就是满足REST架构的风格。

REST，Representational State Transfer，翻译是"表现层状态转化"。

- **资源（Resources）**：REST的名称"表现层状态转化"中，省略了主语。"表现层"其实指的是"资源"（Resources）的"表现层"。而所谓"资源"，就是网络上的一个实体，或者说是网络上的一个具体信息。它可以是一段文本、一张图片、一首歌曲、一种服务，总之就是一个具体的实在。你可以用一个URI（统一资源定位符）指向它，每种资源对应一个特定的URI。要获取这个资源，访问它的URI就可以，因此URI就成了每一个资源的地址或独一无二的识别符。所谓"上网"，就是与互联网上一系列的"资源"互动，调用它的URI。

- **表现层（Representation）**："资源"是一种信息实体，它可以有多种外在表现形式。我们把"资源"具体呈现出来的形式，叫做它的"表现层"（Representation）。比如，文本可以用txt格式表现，也可以用HTML格式、XML格式、JSON格式表现，甚至可以采用二进制格式；图片可以用JPG格式表现，也可以用PNG格式表现。URI只代表资源的实体，不代表它的形式。严格地说，有些网址最后的".html"后缀名是不必要的，因为这个后缀名表示格式，属于"表现层"范畴，而URI应该只代表"资源"的位置。它的具体表现形式，应该在HTTP请求的头信息中用Accept和Content-Type字段指定，这两个字段才是对"表现层"的描述。

- **状态转化（State Transfer）**：访问一个网站，就代表了客户端和服务器的一个互动过程。在这个过程中，势必涉及到数据和状态的变化。互联网通信协议HTTP协议，是一个无状态协议。这意味着，所有的状态都保存在服务器端。因此，如果客户端想要操作服务器，必须通过对资源施加动作，让服务器端发生"状态转化"（State Transfer）。而这种转化是建立在表现层之上的，所以就是"表现层状态转化"。

所以REST的核心就是每一个URI代表一个路径，通过对URL施加不同的动作完成资源状态的转换。施加动作时，客户端和服务器之间传递的参数是资源的表现层。

在REST风格中，定义了七个动作，并且将路径看作资源，通过对资源施加动作完成功能。

| 动作             | 解释                                             |
| ---------------- | ------------------------------------------------ |
| GET（SELECT）    | 从服务器取出资源（一项或多项）                   |
| POST（CREATE）   | 在服务器新建一个资源。                           |
| PUT（UPDATE）    | 在服务器更新资源（客户端提供改变后的完整资源）   |
| PATCH（UPDATE）  | 在服务器更新资源（客户端提供改变的属性）         |
| DELETE（DELETE） | 从服务器删除资源。                               |
| HEAD             | 获取资源的元数据。                               |
| OPTIONS          | 获取信息，关于资源的哪些属性是客户端可以改变的。 |

这种方式对于关系型数据的增删查改非常好用，因为关系型数据存储的都是集合而且有明确的层级关系。如：

| 接口               | 定义                              |
| ------------------ | --------------------------------- |
| 更新用户信息       | PUT /user/{userid}                |
| 给用户增加一个角色 | POST /user/{userid}/role/{roleid} |
| 查看用户的所有角色 | GET /user/{userid}/roles          |
| 删除用户           | DELETE /user/{userid}             |

restful有两个问题：

- 一是其本身的七个动作并不能满足业务上的需求，如登录功能，这个功能最简单的含义是判断用户账户是否存在和密码是否正确，但同时还需要在系统中存一下用户登录的信息，比如登录时间。所以单独的使用GET或者POST都不符合业务需求。
- 二是非关系型数据，restful就不能满足需求了。如审核某人某周的打卡和排班（排班是一个循环的的结构，如135早班246晚班）定义URI：`/user/{userid}/arrangement/{weeknum}`，但其实这个URI并不能表示为系统的一个资源。

#### action

action模式是开发最常见的模式，即URI表示动作，将资源的表现层作为参数传递到后端，同时使用的REST的动作表示接口读写数据和是否幂等。按照这种模式定义上面的接口：

| 接口                     | 定义                                 |
| ------------------------ | ------------------------------------ |
| 更新用户信息             | PUT /user/update?userid=             |
| 给用户增加一个角色       | POST /user/role/insert               |
| 查看用户的所有角色       | GET /user/roles?userid=              |
| 删除用户                 | DELETE /user                         |
| 用户登录                 | POST /user/login                     |
| 审核某人某周的打卡和排班 | GET /user/arrangement/check?weeknum= |

这么模式就是将接口看作动作，调用什么接口就相当于调用什么方法。所以能完全满足业务的需求，不会有表达不出来的问题。但带来的坏处就是接口会很乱，接口的参数和命名会五花八门。

个人在实践中会借鉴REST中资源、表现层、状态转化的概念，尽可能的对结构化的接口（增删查改）做到规范的命名和参数。而对于REST无法描述的场景，就只能按业务行为定义接口了。

### request header

http请求时无状态的，可是用户与系统的交互不是无状态的。这种状态信息不能放在请求路径或请求参数里，应该放在请求头里传递，最经典的就是cookie和token了。

除了和用户状态相关的信息以外，一些固定的属性也需要通过请求头传递。如：

| Header          | Type                      | Description                                                  |
| --------------- | ------------------------- | ------------------------------------------------------------ |
| Accept          | Content type              | 响应请求的内容类型，如:application/xml、text/xml、application/jsonl、text/javascript（for JSONP）根据HTTP准则，这只是一个提示，响应可能有不同的内容类型，例如blob fetch，其中成功的响应将只是blob流作为有效负载。对于遵循OData的服务，应该遵循OData中指定的首选项顺序。 |
| Accept-Encoding | Gzip, deflate             | 如果适用，REST端点应该支持GZIP和DEFLATE编码。对于非常大的资源，服务可能会忽略并返回未压缩的数据。 |
| Accept-Language | “en”, “es”, etc.          | 指定响应的首选语言。不需要服务来支持这一点，但是如果一个服务支持本地化，那么它必须通过Accept-Language头来支持本地化。 |
| Accept-Charset  | Charset type like “UTF-8” | 默认值是UTF-8，但服务应该能够处理ISO-8859-1                  |
| Content-Type    | Content type              | Mime type of request body (PUT/POST/PATCH)                   |

### response

接口的返回值需要能正确的表达出错误的类型。错误类型可以分为三种：服务异常、接口内部错误、业务异常。

其中服务异常是由响应头传递，接口内部错误和业务异常由返回值传递。

接口内部错误是指代码造成的无法请求的场景，如空指针，数组越界，数据库死锁等。这种请求需要能屏蔽内部的错误，对外提供统一的提示信息（系统内部是需要记录异常栈并且对error日志进行监控）。

业务异常是指用户不合法的请求导致的代码无法继续执行的场景，如用户多次登录，但是在用户登录场景中，不合法的请求不止这一个，还有用户不存在，用户选择的角色不存在等问题，所以这些业务异常的状态应该有一个异常状态码来标识。个人使用的返回值数据结构：

```java
public class ControllerResult<T> implements Serializable {

    private String reqId;
    private String version;

    private long ts = System.currentTimeMillis();
    private T result;

    /**
     * 请求状态
     */
    private String reqStatus = StatusConst.FAILED;
    /**
     * 请求错误原因
     */
    private String reqErrorMsg;

    /**
     * 业务状态
     */
    private String bizStatus = StatusConst.FAILED;
    /**
     * 业务失败code
     */
    private Integer bizFailedCode;
    /**
     * 业务失败原因
     */
    private String bizFailedMsg;
}
```

### response header

系统异常时response header的常见响应状态码如下：

|      |                       |                                                              |
| ---- | --------------------- | ------------------------------------------------------------ |
| 400  | Bad Request           | 客户端请求的语法错误，服务器无法理解                         |
| 401  | Unauthorized          | 请求要求用户的身份认证                                       |
| 403  | Forbidden             | 服务器理解请求客户端的请求，但是拒绝执行此请求。如IP黑名单。 |
| 404  | Not Found             | 服务器无法根据客户端的请求找到资源（网页）。通过此代码，网站设计人员可设置"您所请求的资源无法找到"的个性页面 |
| 405  | Method Not Allowed    | 客户端请求中的方法被禁止                                     |
| 406  | Not Acceptable        | 服务器无法根据客户端请求的内容特性完成请求。指代服务器端无法提供与 Accept-Charset 以及 Accept-Language 消息头指定的值相匹配的响应。 |
| 500  | Internal Server Error | 服务器内部错误，无法完成请求                                 |
| 501  | Not Implemented       | 服务器不支持请求的功能，无法完成请求                         |
| 502  | Bad Gateway           | 作为网关或者代理工作的服务器尝试执行请求时，从远程服务器接收到了一个无效的响应 |
| 503  | Service Unavailable   | 由于超载或系统维护，服务器暂时的无法处理客户端的请求。延时的长度可包含在服务器的Retry-After头信息中 |
| 504  | Gateway Time-out      | 充当网关或代理的服务器，未及时从远端服务器获取请求           |

### other

#### limit

查询数据时，往往需要对数据进行过滤、排序、分页等操作，这些条件是放在URI里还是放在请求body里是需要按业务场景而定的，个人常常划分的标准有两条：

- 一是这个链接会不会重试，如被用户复制后发送给他人；
- 二是body里的参数，服务端需不需要创建新的实体类接收，如排序的pageNum和pageSize和分页的orderBy和sort如果都创建实体类会导致实体类过多。但是如果排序的规则太复杂还是要创建实体类接口。

#### version

对于内部接口版本的概念不存在的，往往客户端和服务端都会同时修改和发版，但是对于外部接口版本是必须存在的，版本号是URI的第一部分，如 `/v1/user/create`。



## 参考

- [理解RESTful架构 - 阮一峰的网络日志 (ruanyifeng.com)](https://www.ruanyifeng.com/blog/2011/09/restful.html)
- [RESTful API 设计指南 - 阮一峰的网络日志 (ruanyifeng.com)](https://www.ruanyifeng.com/blog/2014/05/restful_api.html)
- [Microsoft REST API Guidelines中文翻译 – 标点符 (biaodianfu.com)](https://www.biaodianfu.com/microsoft-rest-api-guidelines.html#标准响应标头)
- [restful - 来自于PayPal的RESTful API标准_个人文章 - SegmentFault 思否](https://segmentfault.com/a/1190000005924733)

