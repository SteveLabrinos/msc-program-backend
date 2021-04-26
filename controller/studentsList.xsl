<?xml version="1.0"?>
<xsl:stylesheet version="1.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:template match="/">
    <html>
    <body>
        <h2>Λίστα Φοιτητών <xsl:value-of select="Students/Season"/> εξαμήνου</h2>

        <p>Πλήθος Φοιτητών: <xsl:value-of select="count(Students/Student)"/></p>
        <p>
            Γενικός Μέσος Όρος: <xsl:value-of 
            select="format-number(sum(Students/Student/AverageGrade) div count(Students/Student/AverageGrade), '#.##')"/>
        </p>
        <table border="1">
            <tr bgcolor="#9b1335">
                <th>Επώνυμο</th>
                <th>Όνομα</th>
                <th>Αριθμός Επιτυχών Μαθημάτων</th>
                <th>Μέσος Όρος Μαθημάτων</th>
            </tr>

            <xsl:for-each select="Students/Student">
                <xsl:choose>
                    <xsl:when test="HighGrade">
                        <tr bgcolor="#9acd32">
                            <td><xsl:value-of select="LastName"/></td>
                            <td><xsl:value-of select="FirstName"/></td>
                            <td><xsl:value-of select="CoursesPasses"/></td>
                            <td><xsl:value-of select="AverageGrade"/></td>
                        </tr>
                    </xsl:when>
                    <xsl:otherwise>
                        <tr>
                            <td><xsl:value-of select="LastName"/></td>
                            <td><xsl:value-of select="FirstName"/></td>
                            <td><xsl:value-of select="CoursesPasses"/></td>
                            <td><xsl:value-of select="AverageGrade"/></td>
                        </tr>
                    </xsl:otherwise>
                </xsl:choose>
            </xsl:for-each>
        </table>
    </body>
    </html>
</xsl:template>
</xsl:stylesheet>